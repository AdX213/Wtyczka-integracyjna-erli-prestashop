<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Api/ErliProductApi.php';
require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Mapper/ProductMapper.php';

class ProductSync
{
    /** @var int */
    private $shopId;

    /** @var int */
    private $langId;

    /** @var ErliProductApi */
    private $api;

    // ✅ poprawna nazwa
    const CURSOR_KEY = 'ERLI_PRODUCT_CURSOR_ID';

    public function __construct()
    {
        $ctx = Context::getContext();
        $this->shopId = (int) $ctx->shop->id;
        $this->langId = (int) $ctx->language->id;

        $this->api = new ErliProductApi();
    }

    /**
     * Sync tylko "pending":
     * - last_synced_at IS NULL lub last_error NOT NULL
     */
    public function syncAllPending($limit = 200)
    {
        $limit = max(1, (int) $limit);
        $cursor = $this->getCursor();

        // 1) po cursorze
        $rows = $this->fetchPendingRowsAfterCursor($cursor, $limit);

        // 2) wrap do początku
        if (!$rows) {
            $cursor = 0;
            $rows = $this->fetchPendingRowsAfterCursor($cursor, $limit);
        }

        if (!$rows) {
            return 0;
        }

        $processed = 0;
        $lastIdProcessed = $cursor;

        foreach ($rows as $row) {
            $processed++;
            $lastIdProcessed = (int) $row['id_erli_product_link'];
            $this->syncLinkRow($row);
        }

        $this->setCursor($lastIdProcessed);

        return $processed;
    }

    /**
     * Sync wszystkich zmapowanych produktów (batch).
     */
    public function syncAll($limit = 200)
    {
        $limit = max(1, (int) $limit);
        $cursor = $this->getCursor();

        // 1) po cursorze
        $rows = $this->fetchAllRowsAfterCursor($cursor, $limit);

        // 2) wrap do początku
        if (!$rows) {
            $cursor = 0;
            $rows = $this->fetchAllRowsAfterCursor($cursor, $limit);
        }

        if (!$rows) {
            return 0;
        }

        $processed = 0;
        $lastIdProcessed = $cursor;

        foreach ($rows as $row) {
            $processed++;
            $lastIdProcessed = (int) $row['id_erli_product_link'];
            $this->syncLinkRow($row);
        }

        $this->setCursor($lastIdProcessed);

        return $processed;
    }

    private function getCursor()
    {
        $cursor = (int) Configuration::get(self::CURSOR_KEY);
        return ($cursor < 0) ? 0 : $cursor;
    }

    private function setCursor($id)
    {
        Configuration::updateValue(self::CURSOR_KEY, (int) $id);
    }

    private function fetchPendingRowsAfterCursor($cursor, $limit)
    {
        return Db::getInstance()->executeS(
            'SELECT id_erli_product_link, id_product, id_product_attribute, external_id, last_payload
             FROM `' . _DB_PREFIX_ . 'erli_product_link`
             WHERE (last_synced_at IS NULL OR last_error IS NOT NULL)
               AND external_id IS NOT NULL AND external_id != ""
               AND id_erli_product_link > ' . (int) $cursor . '
             ORDER BY id_erli_product_link ASC
             LIMIT ' . (int) $limit
        );
    }

    private function fetchAllRowsAfterCursor($cursor, $limit)
    {
        return Db::getInstance()->executeS(
            'SELECT id_erli_product_link, id_product, id_product_attribute, external_id, last_payload
             FROM `' . _DB_PREFIX_ . 'erli_product_link`
             WHERE external_id IS NOT NULL AND external_id != ""
               AND id_erli_product_link > ' . (int) $cursor . '
             ORDER BY id_erli_product_link ASC
             LIMIT ' . (int) $limit
        );
    }

    /**
     * Synchronizuje pojedynczy rekord z erli_product_link
     */
    private function syncLinkRow(array $row)
    {
        $linkId     = (int) $row['id_erli_product_link'];
        $idProduct  = (int) $row['id_product'];
        $externalId = (string) $row['external_id'];

        try {
            $product = new Product($idProduct, false, $this->langId, $this->shopId);
            if (!Validate::isLoadedObject($product)) {
                throw new Exception('Nie znaleziono produktu PS id=' . $idProduct);
            }

            // pełny payload mappera
            $payload = ProductMapper::map($product, $this->langId);

            // externalId z linka
            $payload['externalId'] = $externalId;

            // (opcjonalnie, jeśli stock/status ma iść tylko StockSync)
            // unset($payload['stock'], $payload['status']);

            // diff z last_payload
            $lastPayload = $this->decodeJson((string) ($row['last_payload'] ?? ''));
            if (is_array($lastPayload)) {
                $newNorm  = $this->normalizeForCompare($payload);
                $lastNorm = $this->normalizeForCompare($lastPayload);

                if ($newNorm === $lastNorm) {
                    $this->updateLinkSuccess($linkId, $payload, false);
                    return;
                }
            }

            $exists = $this->erliProductExists($externalId);

            $resp = $exists
                ? $this->api->updateProduct($externalId, $payload)
                : $this->api->createProduct($externalId, $payload);

            $code = (int) ($resp['code'] ?? 0);

            if (in_array($code, [200, 201, 202], true)) {
                $this->updateLinkSuccess($linkId, $payload, true);
                return;
            }

            $raw = (string) ($resp['raw'] ?? '');
            $msg = 'ERLI HTTP ' . $code . ': ' . $raw;

            $this->updateLinkError($linkId, $msg);
            $this->logError('product_sync', $externalId, $msg, $payload);
        } catch (Throwable $e) {
            $this->updateLinkError($linkId, $e->getMessage());
            $this->logError('product_sync', $externalId, $e->getMessage(), null);
        }
    }

    private function erliProductExists($externalId)
    {
        $resp = $this->api->getProduct($externalId);
        $code = (int) ($resp['code'] ?? 0);

        if ($code === 404) {
            return false;
        }
        if ($code >= 200 && $code < 300) {
            return true;
        }

        throw new Exception('ERLI check exists failed. HTTP ' . $code . ' raw=' . (string) ($resp['raw'] ?? ''));
    }

    private function updateLinkSuccess($linkId, array $payload, $touchSyncedAt)
    {
        $data = [
            'last_payload' => pSQL($this->stableJsonEncode($payload), true),
            'last_error'   => null,
        ];

        if ($touchSyncedAt) {
            $data['last_synced_at'] = date('Y-m-d H:i:s');
        }

        Db::getInstance()->update(
            'erli_product_link',
            $data,
            'id_erli_product_link=' . (int) $linkId
        );
    }

    private function updateLinkError($linkId, $errorMessage)
    {
        Db::getInstance()->update(
            'erli_product_link',
            [
                'last_error' => pSQL((string) $errorMessage, true),
            ],
            'id_erli_product_link=' . (int) $linkId
        );
    }

    private function logError($type, $referenceId, $message, $payload)
    {
        $repoFile = _PS_MODULE_DIR_ . 'erliintegration/classes/Repository/LogRepository.php';
        if (file_exists($repoFile)) {
            require_once $repoFile;
        }

        if (class_exists('LogRepository')) {
            try {
                $repo = new LogRepository();
                if (method_exists($repo, 'add')) {
                    $repo->add(
                        (string) $type,
                        (string) $referenceId,
                        (string) $message,
                        $payload ? $this->stableJsonEncode($payload) : null
                    );
                    return;
                }
            } catch (Throwable $e) {
                // fallback
            }
        }

        Db::getInstance()->insert('erli_log', [
            'type'         => pSQL((string) $type),
            'reference_id' => pSQL((string) $referenceId),
            'message'      => pSQL((string) $message, true),
            'payload'      => $payload ? pSQL($this->stableJsonEncode($payload), true) : null,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    private function decodeJson($s)
    {
        $s = trim((string) $s);
        if ($s === '') {
            return null;
        }
        $d = json_decode($s, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $d : null;
    }

    private function stableJsonEncode(array $data)
    {
        $data = $this->normalizeForCompare($data);
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function normalizeForCompare($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        $isList = array_keys($value) === range(0, count($value) - 1);
        if ($isList) {
            $out = [];
            foreach ($value as $v) {
                $out[] = $this->normalizeForCompare($v);
            }
            return $out;
        }

        foreach ($value as $k => $v) {
            $value[$k] = $this->normalizeForCompare($v);
        }
        ksort($value);

        return $value;
    }
}
