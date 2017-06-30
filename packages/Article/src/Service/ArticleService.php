<?php

declare(strict_types=1);

namespace Article\Service;

use Article\Filter\ArticleFilter;
use Article\Mapper\ArticleMapper;
use Zend\Paginator\Adapter\DbSelect;
use Zend\Paginator\Paginator;

abstract class ArticleService implements ArticleServiceInterface
{
    private $articleMapper;
    private $articleFilter;

    protected function __construct(ArticleMapper $articleMapper, ArticleFilter $articleFilter)
    {
        $this->articleMapper = $articleMapper;
        $this->articleFilter = $articleFilter;
    }

    public function getPagination($select, $page, $limit)
    {
        $paginatorAdapter = new DbSelect($select, $this->articleMapper->getAdapter());
        $paginator = new Paginator($paginatorAdapter);

        $paginator->setCurrentPageNumber($page);
        $paginator->setItemCountPerPage($limit);

        return $paginator;
    }

    public function getCategoryIds($articleId)
    {
        $categories = [];
        foreach ($this->articleMapper->getCategories($articleId) as $category) {
            $categories[] = $category->category_id;
        }

        return $categories;
    }

    public function delete($articleId)
    {
        $this->articleMapper->delete(['article_uuid' => $articleId]);
    }
}
