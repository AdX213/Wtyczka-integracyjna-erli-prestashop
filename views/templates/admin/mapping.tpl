<div class="panel">
    <h3><i class="icon-sitemap"></i> {l s='Mapowanie kategorii na Erli' mod='erliintegration'}</h3>

    <form method="post">
        <table class="table">
            <thead>
                <tr>
                    <th>{l s='ID kategorii' mod='erliintegration'}</th>
                    <th>{l s='Nazwa kategorii (Presta)' mod='erliintegration'}</th>
                    <th>{l s='ID kategorii Erli' mod='erliintegration'}</th>
                    <th>{l s='Nazwa kategorii Erli' mod='erliintegration'}</th>
                </tr>
            </thead>
            <tbody>
            {if isset($category_rows) && $category_rows && count($category_rows)}
                {foreach from=$category_rows item=row}
                    <tr>
                        <td>{$row.id_category|intval}</td>
                        <td>{$row.category_name|escape:'html':'UTF-8'}</td>
                        <td>
                            <input type="text"
                                   name="category[{$row.id_category|intval}][erli_category_id]"
                                   value="{$row.erli_category_id|escape:'html':'UTF-8'}"
                                   class="form-control" />
                        </td>
                        <td>
                            <input type="text"
                                   name="category[{$row.id_category|intval}][erli_category_name]"
                                   value="{$row.erli_category_name|escape:'html':'UTF-8'}"
                                   class="form-control" />
                        </td>
                    </tr>
                {/foreach}
            {else}
                <tr>
                    <td colspan="4">
                        {l s='Brak kategorii do mapowania.' mod='erliintegration'}
                    </td>
                </tr>
            {/if}
            </tbody>
        </table>

        <button type="submit" name="submitErliSaveCategoryMapping" class="btn btn-primary">
            <i class="icon-save"></i>
            {l s='Zapisz mapowanie kategorii' mod='erliintegration'}
        </button>
    </form>
</div>

<div class="panel">
    <h3><i class="icon-truck"></i> {l s='Mapowanie przewoźników na cenniki Erli' mod='erliintegration'}</h3>

    <form method="post">
        <table class="table">
            <thead>
                <tr>
                    <th>{l s='ID przewoźnika' mod='erliintegration'}</th>
                    <th>{l s='Nazwa przewoźnika' mod='erliintegration'}</th>
                    <th>{l s='Tag Erli (np. KURIER_DPD)' mod='erliintegration'}</th>
                    <th>{l s='Opis / nazwa w Erli' mod='erliintegration'}</th>
                </tr>
            </thead>
            <tbody>
            {if isset($shipping_rows) && $shipping_rows && count($shipping_rows)}
                {foreach from=$shipping_rows item=row}
                    <tr>
                        <td>{$row.id_carrier|intval}</td>
                        <td>{$row.carrier_name|escape:'html':'UTF-8'}</td>
                        <td>
                            <input type="text"
                                   name="shipping[{$row.id_carrier|intval}][erli_tag]"
                                   value="{$row.erli_tag|escape:'html':'UTF-8'}"
                                   class="form-control" />
                        </td>
                        <td>
                            <input type="text"
                                   name="shipping[{$row.id_carrier|intval}][erli_name]"
                                   value="{$row.erli_name|escape:'html':'UTF-8'}"
                                   class="form-control" />
                        </td>
                    </tr>
                {/foreach}
            {else}
                <tr>
                    <td colspan="4">
                        {l s='Brak przewoźników do mapowania.' mod='erliintegration'}
                    </td>
                </tr>
            {/if}
            </tbody>
        </table>

        <button type="submit" name="submitErliSaveShippingMapping" class="btn btn-primary">
            <i class="icon-save"></i>
            {l s='Zapisz mapowanie przewoźników' mod='erliintegration'}
        </button>
    </form>
</div>
