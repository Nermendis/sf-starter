<?php


namespace Core\ClassificationBundle\Admin;

use Core\CkeditorBundle\Form\Type\CKEditorType;
use Doctrine\Common\Util\ClassUtils;
use Core\ClassificationBundle\Entity\Category;
use Knp\Menu\ItemInterface as MenuItemInterface;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\ClassificationBundle\Admin\CategoryAdmin as BaseCategoryAdmin;
use Sonata\ClassificationBundle\Model\CategoryInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyPath;
use Symfony\Component\Security\Acl\Domain\Entry;
use Symfony\Component\Security\Acl\Domain\ObjectIdentity;

class CategoryAdmin extends BaseCategoryAdmin
{
    protected $perPageOptions = array();
    protected $maxPerPage = 9999;

    protected $formOptions = array(
        'validation_groups' => array('admin')
    );

    /** @var  Container */
    protected $container;

    public function setContainer($container)
    {
        $this->container = $container;
    }

    protected function configureFormFields(FormMapper $formMapper)
    {
        parent::configureFormFields($formMapper); // TODO: Change the autogenerated stub

	    $formMapper->with('General', array('class' => 'col-md-6'));
        $formMapper->add('slug');
        $formMapper->end();

        $formMapper->remove('description');

        if ($this->getSubject()->getId()) {
	        $formMapper->with('Details', array('class' => 'col-md-12'));
            $tagName = 'category_'.$this->getSubject()->getId();
            $formMapper->add('description', \FOS\CKEditorBundle\Form\Type\CKEditorType::class, array(
                'label' => 'form.label_description',
                'config_name' => 'default',
            ));
	        $formMapper->end();
        }
    }

    public function toString($object)
    {
        if ($object instanceof CategoryInterface) {
            $path = array($object);
            while($object = $object->getParent()) $path[] = $object;
            return implode(
                array_map(
                    function(CategoryInterface $category) { return (string)$category; },
                    array_reverse($path)
                ),
                ' / '
            );
        } else {
            return parent::toString($object);
        }
    }

    protected function configureListFields(ListMapper $listMapper)
    {
        parent::configureListFields($listMapper);
        $listMapper->remove('position');
        $listMapper->remove('description');
        $listMapper->remove('slug');
    }

    public function preUpdate($object)
    {
        if ($object instanceof Category) {
            if (!$object->getSlug() || $object->getSlug() === 'n-a') {
                $object->setSlug($object->getName());
            }
        }
    }

	public function getNewInstance()
	{
		$instance = parent::getNewInstance();

		if ($this->hasRequest() && ($parentId = $this->getRequest()->get('parent_id'))) {
			$instance->setParent($this->getModelManager()->find($this->getClass(), $parentId));
		}

		return $instance;
	}

	private function getAvailableCategoriesCount()
	{
		$query = $this->getDatagrid()->getQuery();

		return intval($query->select('count(o.id)')->getQuery()->getSingleScalarResult());
	}

    public function buildBreadcrumbs($action, MenuItemInterface $menu = null)
    {
        $result = parent::buildBreadcrumbs($action, $menu);

        if (null === $this->getCurrentChildAdmin()
            && !$this->isChild()
            && 'list' !== $action
            && $this->hasSubject()) {
            /** @var Category $current */
            $current = $this->getSubject();
            $menu = $this->breadcrumbs[$action]->getParent();

            /** @var Category $pathCategory */
            foreach ($current->buildPathCategories() as $pathCategory) {
                $menu = $menu->addChild(
                    $pathCategory->getId() ? $pathCategory->getName() : '+',
                    array('uri' => $pathCategory->getId() ? $this->generateObjectUrl('edit', $pathCategory) : null)
                );
            }

            $result = $menu;
        }

        return $this->breadcrumbs[$action] = $result;
    }


}