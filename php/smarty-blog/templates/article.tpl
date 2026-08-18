{include file="partials/header.tpl" page_title=$article.title}

<article class="article-page">
    <div class="prev-page" >
        <a href="#" onclick="window.history.back()">&larr; Назад</a>
    </div>

    <div class="article-categories">
        {foreach from=$categories item=cat name=cats}
            <a href="/category.php?id={$cat.id|escape:'url'}" class="category-badge">{$cat.name}</a>{if !$smarty.foreach.cats.last}, {/if}
        {/foreach}
    </div>

    <h1 class="article-title">{$article.title}</h1>

    <div class="article-meta">
        <span class="article-date">{$article.published_at|date_format_safe:"%d.%m.%Y"}</span>
        <span class="article-views">{$article.views} просмотров</span>
    </div>

    {if $article.image}
        <img src="{$article.image}" alt="{$article.title|escape}" class="article-image">
    {/if}

    {if $article.description}
        <p class="article-lead">{$article.description}</p>
    {/if}

    <div class="article-content">
        {$article.content|escape|nl2br}
    </div>
</article>

{if !empty($similar)}
    <section class="category-block similar-block">
        <div class="category-block-header">
            <h2 class="category-title">ПОХОЖИЕ СТАТЬИ</h2>
        </div>
        <div class="card-grid">
            {foreach from=$similar item=article}
                {include file="partials/article_card.tpl" article=$article}
            {/foreach}
        </div>
    </section>
{/if}

{include file="partials/footer.tpl"}
