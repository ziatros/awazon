<?php
$this->widget->init('topbar', array(
    "actions" => array(
        NHtml::tag('a', array(
            'href'  => '#',
            'id'    => 'n2-backgroundanimation-editor-cancel',
            'class' => 'n2-button n2-button-red n2-button-big n2-h4 n2-b n2-uc'
        ), n2_('Cancel')),
        NHtml::tag('a', array(
            'href'  => '#',
            'id'    => 'n2-backgroundanimation-editor-save',
            'class' => 'n2-button n2-button-green n2-button-big n2-h4 n2-b n2-uc',
        ), n2_('Apply'))
    ),
    'fixTo'   => false
));
 