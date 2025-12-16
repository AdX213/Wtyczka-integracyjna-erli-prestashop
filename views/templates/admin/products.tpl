<div class="panel">
    <h3><i class="icon-refresh"></i> {l s='Ręczna synchronizacja produktów z Erli.pl' mod='erliintegration'}</h3>

    {$output}

    <form method="post">
        <div class="form-group">
            <label>{l s='ID produktu PrestaShop' mod='erliintegration'}:</label>
            <input type="number" name="ERLI_PRODUCT_ID" class="form-control" />
        </div>

        <button type="submit" name="submitSyncProduct" class="btn btn-primary">
            <i class="icon-upload"></i>
            {l s='Wyślij produkt do Erli' mod='erliintegration'}
        </button>
    </form>
</div>

<div class="panel">
    <h3><i class="icon-list"></i> {l s='Lista produktów PrestaShop' mod='erliintegration'}</h3>

    <table class="table table-striped">
        <thead>
            <tr>|
                <th>{l s='ID' mod='erliintegration'}</th>
                <th>{l s='Zdjęcie' mod='erliintegration'}</th>
                <th>{l s='Nazwa' mod='erliintegration'}</th>
                <th>{l s='Cena' mod='erliintegration'}</th>
                <th>{l s='Stan' mod='erliintegration'}</th>
                <th>{l s='Akcja' mod='erliintegration'}</th>
            </tr>
        </thead>

        <tbody>
        {if $products && count($products)}
            {foreach from=$products item=p}
                <tr>
                    <td>{$p.id_product}</td>

                    <td>
                        {if $p.image}
                            <img src="{$p.image}" alt="" width="50" height="50" />
                        {/if}
                    </td>

                    <td>{$p.name}</td>
                    <td>{$p.price}</td>
                    <td>{$p.quantity}</td>

                    <td>
                        <form method="post" style="margin:0;">
                            <input type="hidden" name="ERLI_PRODUCT_ID" value="{$p.id_product}" />
                            <button class="btn btn-primary" name="submitSyncProduct">
                                <i class="icon-refresh"></i>
                                {l s='Synchronizuj' mod='erliintegration'}
                            </button>
                        </form>
                    </td>
                </tr>
            {/foreach}
        {else}
            <tr>
                <td colspan="6">
                    {l s='Brak produktów.' mod='erliintegration'}
                </td>
            </tr>
        {/if}
        </tbody>
    </table>
</div>

<div class="panel">
    <h3><i class="icon-list"></i> {l s='Ostatnie logi synchronizacji' mod='erliintegration'}</h3>

    <table class="table">
        <thead>
            <tr>
                <th>{l s='Data' mod='erliintegration'}</th>
                <th>{l s='Typ' mod='erliintegration'}</th>
                <th>{l s='Referencja' mod='erliintegration'}</th>
                <th>{l s='Komunikat' mod='erliintegration'}</th>
            </tr>
        </thead>
        <tbody>
        {if $logs && count($logs)}
            {foreach from=$logs item=log}
                <tr>
                    <td>{$log.created_at}</td>
                    <td>{$log.type}</td>
                    <td>{$log.reference_id}</td>
                    <td>{$log.message}</td>
                </tr>
            {/foreach}
        {else}
            <tr>
                <td colspan="4">
                    {l s='Brak logów.' mod='erliintegration'}
                </td>
            </tr>
        {/if}
        </tbody>
    </table>
</div>
