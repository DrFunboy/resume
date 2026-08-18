{include file="partials/header.tpl"}

{if empty($blocks)}
    <p class="empty-state">Пока нет опубликованных статей.</p>
{else}
    {foreach from=$blocks item=block}
        <section class="category-block">
            <div class="category-block-header">
                <h2 class="category-title">{$block.category.name|upper}</h2>
                <a href="/category.php?id={$block.category.id|escape:'url'}" class="view-all">Все статьи</a>
            </div>

            <div class="card-grid">
                {foreach from=$block.articles item=article}
                    {include file="partials/article_card.tpl" article=$article}
                {/foreach}
            </div>
        </section>
    {/foreach}
{/if}

{include file="partials/footer.tpl"}
