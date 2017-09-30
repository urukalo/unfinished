<?php

declare(strict_types=1);

namespace Menu\Service;

use Category\Service\CategoryService;
use Menu\Filter\MenuFilter;
use Menu\Mapper\MenuMapper;
use MysqlUuid\Formats\Binary;
use MysqlUuid\Uuid as MysqlUuid;
use Page\Service\PageService;
use Ramsey\Uuid\Uuid;
use Std\FilterException;
use Zend\Db\Sql\Expression;

class MenuService
{
    private $menuMapper;
    private $menuFilter;
    private $categoryService;
    private $pageService;

    public function __construct(
        MenuMapper $menuMapper,
        MenuFilter $menuFilter,
        CategoryService $categoryService,
        PageService $pageService
    ) {
        $this->menuMapper = $menuMapper;
        $this->menuFilter = $menuFilter;
        $this->categoryService = $categoryService;
        $this->pageService = $pageService;
    }

    /**
     * We store menu items in DB as flat structure,
     * but we need nested(tree) structure to show in the menu.
     *
     * @param array    $flatArray Array from DB
     * @param int|bool $parent
     *
     * @return array Return same array with tree structure
     */
    private function buildTree(array $flatArray, $parent = null)
    {
        $result = [];

        foreach ($flatArray as $element) {
            if ($element['parent_id'] == $parent) {
                $children = $this->buildTree($flatArray, $element['menu_id']);
                $element['children'] = ($children) ? $children : [];
                $result[] = $element;
            }
        }

        return $result;
    }

    public function getNestedAll($isActive = null, $filter = [])
    {
        $items = $this->menuMapper->selectAll($isActive, $filter)->toArray();

        return $this->buildTree($items);
    }

    public function get($id)
    {
        return $this->menuMapper->get($id);
    }

    public function addMenuItem($data)
    {
        $data  = $this->filterMenuItem($data);
        $order = $this->getMaxParentsOrderNumber();

        $data['menu_id'] = Uuid::uuid1()->toString();
        $data['menu_uuid'] = (new MysqlUuid($data['menu_id']))->toFormat(new Binary());
        $data['order_no'] = (!empty($order)) ? ($order + 1) : 1;

        return $this->menuMapper->insertMenuItem($data);
    }

    public function updateMenuItem($data, $id)
    {
        $data = $this->filterMenuItem($data);

        return $this->menuMapper->updateMenuItem($data, $id);
    }

    public function delete($id)
    {
        $menu     = $this->menuMapper->select(['menu_id' => $id]);
        $children = $this->menuMapper->select(['parent_id' => $id]);

        if ($children->count()) {
            $menu = $menu->current();
            $this->menuMapper->update([
                'order_no'  => new Expression('order_no + ' . ($children->count() - 1))], [
                'parent_id' => $menu->parent_id,
                'order_no'  => new Expression('order_no > ' . ($menu->order_no))
            ]);

            foreach ($children->toArray() as $child) {
                $child['parent_id'] = $menu->parent_id;
                $child['order_no'] += ($menu->order_no - 1);
                $this->menuMapper->update($child, [
                    'menu_id' => $child['menu_id']
                ]);
            }
        }

        return $this->menuMapper->delete(['menu_id' => $id]);
    }

    public function getForSelect()
    {
        return $this->menuMapper->forSelect();
    }

    public function updateMenuOrder($menuOrder)
    {
        if (!$menuOrder) {
            return true;
        }

        try {
            $this->menuMapper->getAdapter()->getDriver()->getConnection()->beginTransaction();
            $this->updateLevel($menuOrder, null);
            $this->menuMapper->getAdapter()->getDriver()->getConnection()->commit();
        } catch (\Exception $e) {
            $this->menuMapper->getAdapter()->getDriver()->getConnection()->rollback();

            throw $e;
        }

        return true;
    }

    private function updateLevel($children, $parentId = null)
    {
        $i=0; foreach ($children as $v) { $i++;
            if (isset($v->children)) {
                $this->menuMapper->update(['order_no' => $i, 'parent_id' => $parentId], ['menu_id' => $v->id]);
                $this->updateLevel($v->children, $v->id);
            } else {
                $this->menuMapper->update(['order_no' => $i, 'parent_id' => $parentId], ['menu_id' => $v->id]);
            }
        }
    }

    private function filterMenuItem($data)
    {
        $filter = $this->menuFilter->getInputFilter()->setData($data);

        if (!$filter->isValid()) {
            throw new FilterException($filter->getMessages());
        }

        if (count(array_filter([$data['page_id'], $data['category_id'], $data['href']])) > 1) {
            throw new \Exception('You need to set only one link. Post, Category or Href.');
        }

        $data = $filter->getValues();

        if ($data['page_id']) {
            $page = $this->pageService->getPage($data['page_id']);
            $data['page_uuid'] = $page->getPageUuid();
            $data['category_uuid'] = null;
        } elseif ($data['category_id']) {
            $category
                = $this->categoryService->getCategory($data['category_id']);
            $data['category_uuid'] = $category->category_uuid;
            $data['page_uuid'] = null;
        } else {
            $data['page_uuid'] = null;
            $data['category_uuid'] = null;
        }

        unset($data['page_id'], $data['category_id']);

        return $data;
    }

    /**
     * Returns max order number if found.
     *
     * @return integer|null
     */
    private function getMaxParentsOrderNumber()
    {
        $select = $this->menuMapper
            ->getSql()
            ->setTable('menu')
            ->select()
            ->where('parent_id IS NULL')
            ->columns(['order_no' => new Expression('MAX(order_no)')])
        ;

        $result = $this->menuMapper->selectWith($select)->current();
        return $result->order_no;
    }
}
