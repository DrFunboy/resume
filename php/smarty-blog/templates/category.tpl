{include file="partials/header.tpl" page_title=$category.name}

<section class="category-page">
    <div class="category-page-header">
        <h1 class="category-title">{$category.name|upper}</h1>
        {if $category.description}
            <p class="category-description">{$category.description}</p>
        {/if}
    </div>

    <div class="sort-bar">
        <span class="sort-label">Сортировка:</span>
        <a href="/category.php?id={$category.id|escape:'url'}&sort=date"
           class="sort-link{if $sort == 'date'} active{/if}">По дате</a>
        <a href="/category.php?id={$category.id|escape:'url'}&sort=views"
           class="sort-link{if $sort == 'views'} active{/if}">По просмотрам</a>
        <span class="sort-total">{$total_articles} статей</span>
    </div>

    {if empty($articles)}
        <p class="empty-state">В этой категории пока нет статей.</p>
    {else}
        <div class="card-grid">
            {foreach from=$articles item=article}
                {include file="partials/article_card.tpl" article=$article}
            {/foreach}
        </div>

        {include file="partials/pagination.tpl"}
    {/if}
</section>

{include file="partials/footer.tpl"}
