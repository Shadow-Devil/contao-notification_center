<?php

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2011 Leo Feyer
 *
 * Formerly known as TYPOlight Open Source CMS.
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  terminal42 gmbh 2013
 * @license    LGPL
 */

$this->loadDataContainer('tl_nc_gateway');

/**
 * Table tl_nc_language
 */
$GLOBALS['TL_DCA']['tl_nc_language'] = array
(

    // Config
    'config' => array
    (
        'ptable'                      => 'tl_nc_message',
        'dataContainer'               => 'Table',
        'enableVersioning'            => true,
        'nc_type_query'               => "SELECT type FROM tl_nc_notification WHERE id=(SELECT pid FROM tl_nc_message WHERE id=(SELECT pid FROM tl_nc_language WHERE id=?))",
        'oncreate_callback' => array
        (
            array('NotificationCenter\tl_nc_language', 'insertGatewayType'),
        ),
        'onload_callback'             => array
        (
            array('NotificationCenter\AutoSuggester', 'load')
        ),
        'sql' => array
        (
            'keys' => array
            (
                'id'         => 'primary',
                'pid'        => 'index',
                'language'   => 'index'
            )
        ),
    ),

    // List
    'list' => array
    (
        'sorting' => array
        (
            'mode'                    => 1,
            'fields'                  => array('language'),
            'flag'                    => 1
        ),
        'label' => array
        (
            'fields'                  => array('language', 'fallback'),
            'format'                  => '%s <span style="color:#b3b3b3; padding-left:3px;">[%s]</span>',
        ),
        'global_operations' => array
        (
            'all' => array
            (
                'label'               => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'                => 'act=select',
                'class'               => 'header_edit_all',
                'attributes'          => 'onclick="Backend.getScrollOffset()" accesskey="e"'
            )
        ),
        'operations' => array
        (
            'edit' => array
            (
                'label'               => &$GLOBALS['TL_LANG']['tl_nc_language']['edit'],
                'href'                => 'act=edit',
                'icon'                => 'edit.gif'
            ),
            'copy' => array
            (
                'label'               => &$GLOBALS['TL_LANG']['tl_nc_language']['copy'],
                'href'                => 'act=copy',
                'icon'                => 'copy.gif'
            ),
            'delete' => array
            (
                'label'               => &$GLOBALS['TL_LANG']['tl_nc_language']['delete'],
                'href'                => 'act=delete',
                'icon'                => 'delete.gif',
                'attributes'          => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"'
            ),
            'show' => array
            (
                'label'               => &$GLOBALS['TL_LANG']['tl_nc_language']['show'],
                'href'                => 'act=show',
                'icon'                => 'show.gif'
            )
        )
    ),

    // Palettes
    'palettes' => array
    (
        '__selector__'                => array('gateway_type', 'email_mode'),
        'default'                     => '{general_legend},language,fallback',
        'email'                       => '{general_legend},language,fallback,recipients;{attachments_legend},attachments;{gateway_legend},email_sender,email_subject,email_mode',
    ),

    'subpalettes' => array
    (
        'email_mode_textOnly'         => 'email_text',
        'email_mode_textAndHtml'      => 'email_text,email_html',
    ),

    // Fields
    'fields' => array
    (
        'id' => array
        (
            'sql'                     => "int(10) unsigned NOT NULL auto_increment"
        ),
        'pid' => array
        (
            'foreignKey'              => 'tl_nc_message.title',
            'sql'                     => "int(10) unsigned NOT NULL default '0'",
            'relation'                => array('type'=>'belongsTo', 'load'=>'lazy')
        ),
        'tstamp' => array
        (
            'sql'                     => "int(10) unsigned NOT NULL default '0'"
        ),
        'gateway_type' => array
        (
            // This is only to select the palette
            'eval'                    => array('doNotShow'=>true),
            'sql'                     => &$GLOBALS['TL_DCA']['tl_nc_gateway']['fields']['type']['sql'],
        ),
        'language' => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_nc_language']['language'],
            'exclude'                 => true,
            'default'                 => $GLOBALS['TL_LANGUAGE'],
            'inputType'               => 'select',
            'options'                 => \System::getLanguages(),
            'eval'                    => array('mandatory'=>true, 'unique'=>true, 'chosen'=>true, 'tl_class'=>'w50'),
            'sql'                     => "varchar(5) NOT NULL default ''"
        ),
        'fallback' => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_nc_language']['fallback'],
            'exclude'                 => true,
            'inputType'               => 'checkbox',
            'eval'                    => array('fallback'=>true, 'tl_class'=>'w50 m12'),
            'sql'                     => "char(1) NOT NULL default ''"
        ),
        'recipients' => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_nc_language']['recipients'],
            'exclude'                 => true,
            'inputType'               => 'text',
            'eval'                    => array('rgxp'=>'nc_tokens', 'tl_class'=>'long clr', 'decodeEntities'=>true, 'mandatory'=>true),
            'sql'                     => "varchar(255) NOT NULL default ''"
        ),
        'attachments' => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_nc_language']['attachments'],
            'exclude'                 => true,
            'inputType'               => 'text',
            'eval'                    => array('rgxp'=>'nc_tokens', 'tl_class'=>'long clr', 'decodeEntities'=>true),
            'sql'                     => "varchar(255) NOT NULL default ''"
        ),
        'email_sender' => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_nc_language']['email_sender'],
            'exclude'                 => true,
            'inputType'               => 'text',
            'eval'                    => array('tl_class'=>'long clr', 'rgxp'=>'friendly', 'mandatory'=>true),
            'sql'                     => "varchar(255) NOT NULL default ''"
        ),
        'email_subject' => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_nc_language']['email_subject'],
            'exclude'                 => true,
            'inputType'               => 'text',
            'eval'                    => array('rgxp'=>'nc_tokens', 'tl_class'=>'long clr', 'decodeEntities'=>true, 'mandatory'=>true),
            'sql'                     => "varchar(255) NOT NULL default ''"
        ),
        'email_mode' => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_nc_language']['email_mode'],
            'exclude'                 => true,
            'default'                 => 'textOnly',
            'inputType'               => 'radio',
            'options'                 => array('textOnly', 'textAndHtml'),
            'reference'               => &$GLOBALS['TL_LANG']['tl_nc_language']['email_mode'],
            'eval'                    => array('tl_class'=>'clr', 'submitOnChange'=>true),
            'sql'                     => "varchar(16) NOT NULL default ''"
        ),
        'email_text' => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_nc_language']['email_text'],
            'exclude'                 => true,
            'inputType'               => 'textarea',
            'eval'                    => array('rgxp'=>'nc_tokens', 'tl_class'=>'clr', 'decodeEntities'=>true, 'mandatory'=>true),
            'sql'                     => "text NULL"
        ),
        'email_html' => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_nc_language']['email_html'],
            'exclude'                 => true,
            'inputType'               => 'textarea',
            'eval'                    => array('rgxp'=>'nc_tokens', 'tl_class'=>'clr', 'rte'=>'tinyMCE', 'decodeEntities'=>true, 'allowHtml'=>true, 'mandatory'=>true),
            'sql'                     => "text NULL"
        )
    )
);