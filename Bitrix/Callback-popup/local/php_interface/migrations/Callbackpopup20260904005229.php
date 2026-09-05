<?php

namespace Sprint\Migration;

class Callbackpopup20260904005229 extends Version
{
    protected $author = "admin";

    protected $description = "Веб форма для модалки обратного звонка";

    protected $moduleVersion = "5.13.0";

    /**
     * @return bool|void
     * @throws Exceptions\HelperException
     */
    public function up()
    {
        $helper = $this->getHelperManager();
        $formId = $helper->Form()->saveForm(array(
            'NAME' => 'Обратный звонок',
            'SID' => 'SIMPLE_FORM_1',
            'DESCRIPTION' => 'Форма обратного звонка для модального окна',
            'MAIL_EVENT_TYPE' => 'FORM_FILLING_SIMPLE_FORM_1',
            'arSITE' =>
                array(
                    0 => 's1',
                ),
            'arMENU' =>
                array(
                    'ru' => '',
                    'en' => '',
                ),
            'arGROUP' =>
                array(),
            'arMAIL_TEMPLATE' =>
                array(),
        ));
        $helper->Form()->saveStatuses($formId, array(
            0 =>
                array(
                    'CSS' => 'statusgreen',
                    'TITLE' => 'DEFAULT',
                ),
        ));
        $helper->Form()->saveFields($formId, array(
            0 =>
                array(
                    'TITLE' => 'Ваше имя',
                    'TITLE_TYPE' => 'text',
                    'SID' => 'SIMPLE_QUESTION_577',
                    'REQUIRED' => 'Y',
                    'FILTER_TITLE' => 'Ваше имя',
                    'RESULTS_TABLE_TITLE' => 'Ваше имя',
                    'ANSWERS' =>
                        array(
                            0 =>
                                array(
                                    'FIELD_TYPE' => 'text',
                                ),
                        ),
                    'VALIDATORS' =>
                        array(),
                ),
            1 =>
                array(
                    'TITLE' => 'Телефон',
                    'TITLE_TYPE' => 'text',
                    'SID' => 'SIMPLE_QUESTION_295',
                    'C_SORT' => '200',
                    'REQUIRED' => 'Y',
                    'FILTER_TITLE' => 'Телефон',
                    'RESULTS_TABLE_TITLE' => 'Телефон',
                    'ANSWERS' =>
                        array(
                            0 =>
                                array(
                                    'FIELD_TYPE' => 'text',
                                ),
                        ),
                    'VALIDATORS' =>
                        array(),
                ),
            2 =>
                array(
                    'TITLE' => 'Комментарий',
                    'TITLE_TYPE' => 'text',
                    'SID' => 'SIMPLE_QUESTION_999',
                    'C_SORT' => '300',
                    'FILTER_TITLE' => 'Комментарий',
                    'RESULTS_TABLE_TITLE' => 'Комментарий',
                    'ANSWERS' =>
                        array(
                            0 =>
                                array(
                                    'FIELD_TYPE' => 'textarea',
                                ),
                        ),
                    'VALIDATORS' =>
                        array(),
                ),
            3 =>
                array(
                    'TITLE' => 'Согласие на обработку данных',
                    'TITLE_TYPE' => 'text',
                    'SID' => 'SIMPLE_QUESTION_193',
                    'C_SORT' => '400',
                    'REQUIRED' => 'Y',
                    'FILTER_TITLE' => 'Согласие на обработку данных',
                    'RESULTS_TABLE_TITLE' => 'Согласие на обработку данных',
                    'ANSWERS' =>
                        array(
                            0 =>
                                array(
                                    'FIELD_TYPE' => 'checkbox',
                                ),
                        ),
                    'VALIDATORS' =>
                        array(),
                ),
        ));
    }
}

