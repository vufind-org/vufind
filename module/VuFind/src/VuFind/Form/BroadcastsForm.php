<?php

/**
 * Form for broadcasts
 *
 * PHP version 8
 *
 * Copyright (C) effective WEBWORK GmbH 2023.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Form;

use Laminas\Form\Element\Checkbox;
use Laminas\Form\Element\Date;
use Laminas\Form\Element\Radio;
use Laminas\Form\Element\Submit;
use Laminas\Form\Element\Textarea;
use Laminas\Form\Form;
use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\InputFilter\InputFilterProviderInterface;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;

/**
 * Form for broadcasts
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class BroadcastsForm extends Form implements InputFilterProviderInterface, TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    /**
     * Notifications config
     *
     * @var mixed
     */
    protected $config;

    /**
     * Constructor
     *
     * @param TranslatorInterface $translator Translator interface
     * @param mixed               $config     Notifications config
     */
    public function __construct(TranslatorInterface $translator, $config)
    {
        parent::__construct();
        $this->setTranslator($translator);
        $this->config = $config;
        $this->setName('notifications-form');
    }

    /**
     * Initialize the form
     */
    public function init(): void
    {
        $this->add([
            'name' => 'visibility',
            'type' => Checkbox::class,
            'options' => [
                'label' => $this->translate('broadcasts_visible'),
            ],
            'attributes' => [
            ],
        ]);

        $this->add([
            'name' => 'visibility_global',
            'type' => Checkbox::class,
            'options' => [
                'label' => $this->translate('broadcasts_visible_global'),
            ],
            'attributes' => [
            ],
        ]);

        foreach ($this->config['Notifications']['languages'] as $language) {
            $this->add([
                'name' => 'content_' . $language,
                'type' => Textarea::class,
                'options' => [
                    'label' => $this->translate('notifications_content'),
                ],
                'attributes' => [
                    'class' => 'form-control',
                    'rows' => '6s',
                ],
            ]);
        }

        $colors = [];
        foreach ($this->config['Notifications']['broadcast_types'] as $type => $typeData) {
            if (isset($typeData['background_color'])) {
                $colors[] = [
                    'value' => $type,
                    'label' => '',
                    'attributes' => [
                        //'style' => 'display: none;',
                    ],
                    'label_attributes' => [
                        'class' => 'notifications-color',
                        'style' => 'outline:1px solid ' . $typeData['border_color'] . '; background-color:' . $typeData['background_color'] . ';',
                    ],
                ];
            }
        }
        $this->add([
            'name' => 'color',
            'type' => Radio::class,
            'options' => [
                'label' => $this->translate('broadcasts_color'),
                'value_options' => $colors,
            ],
            'attributes' => [
                'class' => 'form-control',
                'rows' => '12',
            ],
        ]);

        $this->add([
            'name' => 'startdate',
            'type' => Date::class,
            'options' => [
                'label' => $this->translate('broadcasts_startdate'),
                'format' => 'Y-m-d',
            ],
            'attributes' => [
                'class' => 'form-control',
            ],
        ]);

        $this->add([
            'name' => 'enddate',
            'type' => Date::class,
            'options' => [
                'label' => $this->translate('broadcasts_enddate'),
                'format' => 'Y-m-d',
            ],
            'attributes' => [
                'class' => 'form-control',
            ],
        ]);

        // Submit
        $this->add([
            'name' => 'submit',
            'type' => Submit::class,
            'attributes' => [
                'class' => 'btn btn-primary',
            ],
        ]);

        // Cancel
        $this->add([
            'name' => 'cancel',
            'type' => Submit::class,
            'attributes' => [
                'class' => 'btn btn-secondary',
            ],
        ]);
    }

    /**
     * Get specifications for the form
     */
    public function getInputFilterSpecification(): array
    {
        $inputFilterSpecifications = [];

        foreach ($this->config['Notifications']['languages'] as $language) {
            /*
            $inputFilterSpecifications[] = [
                    'name' => 'visibility_'.$language,
                    'required' => true,
                    'filters' => [
                    ],
                    'validators' => [
                    ],
                ];
            $inputFilterSpecifications[] = [
                    'name' => 'headline_'.$language,
                    'required' => true,
                    'filters' => [
                    ],
                    'validators' => [
                    ],
                ];
            $inputFilterSpecifications[] = [
                    'name' => 'nav_title_'.$language,
                    'required' => true,
                    'filters' => [
                    ],
                    'validators' => [
                    ],
                ];
            $inputFilterSpecifications[] = [
                    'name' => 'content_'.$language,
                    'required' => true,
                    'filters' => [
                    ],
                    'validators' => [
                    ],
                ];
            */
        }

        return $inputFilterSpecifications;
    }
}
