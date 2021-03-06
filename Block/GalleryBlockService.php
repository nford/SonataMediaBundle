<?php
/*
 * This file is part of the Sonata project.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\MediaBundle\Block;

use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Admin\Admin;
use Sonata\AdminBundle\Validator\ErrorElement;

use Sonata\BlockBundle\Model\BlockInterface;
use Sonata\BlockBundle\Block\BaseBlockService;

use Sonata\MediaBundle\Model\GalleryManagerInterface;
use Sonata\MediaBundle\Model\GalleryInterface;
use Sonata\MediaBundle\Model\MediaInterface;

use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * PageExtension
 *
 * @author     Thomas Rabaix <thomas.rabaix@sonata-project.org>
 */
class GalleryBlockService extends BaseBlockService
{
    protected $galleryAdmin;

    protected $galleryManager;

    /**
     * @param string                                                    $name
     * @param \Symfony\Component\Templating\EngineInterface             $templating
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     * @param \Sonata\MediaBundle\Model\GalleryManagerInterface         $galleryManager
     */
    public function __construct($name, EngineInterface $templating, ContainerInterface $container, GalleryManagerInterface $galleryManager)
    {
        parent::__construct($name, $templating);

        $this->galleryManager = $galleryManager;
        $this->container      = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'Gallery';
    }

    /**
     * @return \Sonata\MediaBundle\Provider\Pool
     */
    public function getMediaPool()
    {
        return $this->container->get('sonata.media.pool');
    }

    /**
     * @return \Sonata\AdminBundle\Admin\AdminInterface
     */
    public function getGalleryAdmin()
    {
        if (!$this->galleryAdmin) {
            $this->galleryAdmin = $this->container->get('sonata.media.admin.gallery');
        }

        return $this->galleryAdmin;
    }

    /**
     * {@inheritdoc}
     */
    public function validateBlock(ErrorElement $errorElement, BlockInterface $block)
    {

    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultSettings()
    {
        return array(
            'gallery'  => false,
            'title'    => false,
            'context'  => false,
            'format'   => false,
            'pauseTime' => 3000,
            'animSpeed' => 300,
            'startPaused' => false,
            'directionNav' => true,
            'progressBar' => true,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function buildEditForm(FormMapper $formMapper, BlockInterface $block)
    {
        $contextChoices = array();

        foreach ($this->getMediaPool()->getContexts() as $name => $context) {
            $contextChoices[$name] = $name;
        }

        $gallery = $block->getSetting('galleryId');

        $formatChoices = array();

        if ($gallery instanceof GalleryInterface) {

            $formats = $this->getMediaPool()->getFormatNamesByContext($gallery->getContext());

            foreach ($formats as $code => $format) {
                $formatChoices[$code] = $code;
            }
        }

        // simulate an association ...
        $fieldDescription = $this->getGalleryAdmin()->getModelManager()->getNewFieldDescriptionInstance($this->getGalleryAdmin()->getClass(), 'media' );
        $fieldDescription->setAssociationAdmin($this->getGalleryAdmin());
        $fieldDescription->setAdmin($formMapper->getAdmin());
        $fieldDescription->setOption('edit', 'list');
        $fieldDescription->setAssociationMapping(array('fieldName' => 'gallery', 'type' => \Doctrine\ORM\Mapping\ClassMetadataInfo::MANY_TO_ONE));

        $builder = $formMapper->create('galleryId', 'sonata_type_model', array(
            'sonata_field_description' => $fieldDescription,
            'class'             => $this->getGalleryAdmin()->getClass(),
            'model_manager'     => $this->getGalleryAdmin()->getModelManager()
        ));

        $formMapper->add('settings', 'sonata_type_immutable_array', array(
            'keys' => array(
                array('title', 'text', array('required' => false)),
                array('context', 'choice', array('required' => true, 'choices' => $contextChoices)),
                array('format', 'choice', array('required' => count($formatChoices) > 0, 'choices' => $formatChoices)),
                array($builder, null, array()),
                array('pauseTime', 'number', array()),
                array('animSpeed', 'number', array()),
                array('startPaused', 'sonata_type_boolean', array()),
                array('directionNav', 'sonata_type_boolean', array()),
                array('progressBar', 'sonata_type_boolean', array()),
            )
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function execute(BlockInterface $block, Response $response = null)
    {
        // merge settings
        $settings = array_merge($this->getDefaultSettings(), $block->getSettings());

        $gallery = $settings['galleryId'];

        return $this->renderResponse('SonataMediaBundle:Block:block_gallery.html.twig', array(
            'gallery'   => $gallery,
            'block'     => $block,
            'settings'  => $settings,
            'elements'  => $gallery ? $this->buildElements($gallery) : array(),
        ), $response);
    }

    /**
     * {@inheritdoc}
     */
    public function load(BlockInterface $block)
    {
        $media = $block->getSetting('galleryId', null);

        if ($media) {
            $media = $this->galleryManager->findOneBy(array('id' => $media));
        }

        $block->setSetting('galleryId', $media);
    }

    /**
     * {@inheritdoc}
     */
    public function prePersist(BlockInterface $block)
    {
        $block->setSetting('galleryId', is_object($block->getSetting('galleryId')) ? $block->getSetting('galleryId')->getId() : null);
    }

    /**
     * {@inheritdoc}
     */
    public function preUpdate(BlockInterface $block)
    {
        $block->setSetting('galleryId', is_object($block->getSetting('galleryId')) ? $block->getSetting('galleryId')->getId() : null);
    }

    /**
     * {@inheritdoc}
     */
    public function getStylesheets($media)
    {
        return array(
            '/bundles/sonatamedia/nivo-gallery/nivo-gallery.css'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getJavacripts($media)
    {
        return array(
            '/bundles/sonatamedia/nivo-gallery/jquery.nivo.gallery.js'
        );
    }

    /**
     * @param \Sonata\MediaBundle\Model\GalleryInterface $gallery
     *
     * @return array
     */
    private function buildElements(GalleryInterface $gallery)
    {
        $elements = array();
        foreach ($gallery->getGalleryHasMedias() as $galleryHasMedia) {
            if (!$galleryHasMedia->getEnabled()) {
                continue;
            }

            $type = $this->getMediaType($galleryHasMedia->getMedia());

            if (!$type) {
                continue;
            }

            $elements[] = array(
                'title'     => $galleryHasMedia->getMedia()->getName(),
                'caption'   => $galleryHasMedia->getMedia()->getDescription(),
                'type'      => $type,
                'media'     => $galleryHasMedia->getMedia(),
            );
        }

        return $elements;
    }

    /**
     * @param \Sonata\MediaBundle\Model\MediaInterface $media
     *
     * @return false|string
     */
    private function getMediaType(MediaInterface $media)
    {
        if ($media->getContentType() == 'video/x-flv') {
            return 'video';
        } elseif (substr($media->getContentType(), 0, 5) == 'image') {
            return 'image';
        }

        return false;
    }
}
