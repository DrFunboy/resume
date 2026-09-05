<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/** @var array $arResult */
/** @var array $arParams */
/** @var CMain $APPLICATION */

$fields = [];
function pp($data)
{
    echo "<pre>";
    var_dump($data);
    echo "</pre>";
}


foreach ($arResult['QUESTIONS'] as $sid => $question) {
    $questionData = $question['STRUCTURE'][0];
    $fieldId = $questionData['ID'] ?? 0;

    $field = [
        'id' => $fieldId,
        'sid' => $sid,
        'title' => $question['CAPTION'],
        'required' => $question['REQUIRED'] === 'Y',
        'type' => $questionData['FIELD_TYPE'],
        'value' => '',
        'errors' => [],
    ];

    if ($field['type'] === 'checkbox') {
        $field['checked'] = false;
        $field['question_id'] = $questionData;
        if (isset($questionData['FIELD_PARAM']) && $questionData['FIELD_PARAM'] === 'checked') {
            $field['checked'] = true;
        }
    }

    $fields[] = $field;
}

$jsonFields = json_encode($fields, JSON_UNESCAPED_UNICODE);
$formId = intval($arParams['WEB_FORM_ID']);

?>

<div id="callbackApp">
<!--    Кнопка для тестирования -->
    <button @click="openModal" class="btn btn-primary" style="position:fixed;bottom:20px;right:20px;z-index:999">
        Открыть форму
    </button>

<!--    Модальное окно -->
    <div class="modal fade" id="callbackModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Обратный звонок</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form @submit.prevent="submitForm" novalidate>
                        <div v-for="field in fields" :key="field.id" class="mb-3">
<!--                            Текстовое поле-->
                            <div v-if="field.type === 'text'">
                                <label :for="'field_'+field.id" class="form-label">
                                    {{ field.title }}
                                    <span v-if="field.required" class="text-danger">*</span>
                                </label>
                                <input
                                        :id="'field_'+field.id"
                                        type="text"
                                        class="form-control"
                                        :class="{'is-invalid': field.errors.length > 0}"
                                        v-model="field.value"
                                        :placeholder="field.placeholder"
                                        @blur="validateField(field)"
                                />
                                <div v-if="field.errors.length > 0" class="invalid-feedback">
                                    <span v-for="error in field.errors" :key="error">{{ error }}</span>
                                </div>
                            </div>

<!--                            Textarea -->
                            <div v-else-if="field.type === 'textarea'">
                                <label :for="'field_'+field.id" class="form-label">
                                    {{ field.title }}
                                    <span v-if="field.required" class="text-danger">*</span>
                                </label>
                                <textarea
                                        :id="'field_'+field.id"
                                        class="form-control"
                                        :class="{'is-invalid': field.errors.length > 0}"
                                        v-model="field.value"
                                        :placeholder="field.placeholder"
                                        rows="3"
                                        @blur="validateField(field)"
                                ></textarea>
                                <div v-if="field.errors.length > 0" class="invalid-feedback">
                                    <span v-for="error in field.errors" :key="error">{{ error }}</span>
                                </div>
                            </div>

<!--                            Checkbox -->
                            <div v-else-if="field.type === 'checkbox'" class="form-check">
                                <input
                                        :id="'field_'+field.id"
                                        type="checkbox"
                                        class="form-check-input"
                                        :class="{'is-invalid': field.errors.length > 0}"
                                        v-model="field.checked"
                                        @change="validateField(field)"
                                />
                                <label :for="'field_'+field.id" class="form-check-label">
                                    {{ field.title }}
                                    <span v-if="field.required" class="text-danger">*</span>
                                </label>
                                <div v-if="field.errors.length > 0" class="invalid-feedback">
                                    <span v-for="error in field.errors" :key="error">{{ error }}</span>
                                </div>
                            </div>

                        </div>

                        <div v-if="submitError" class="alert alert-danger">{{ submitError }}</div>
                        <div v-if="submitSuccess" class="alert alert-success">{{ submitSuccess }}</div>

                        <button type="submit" class="btn btn-primary w-100" :disabled="loading">
                            <span v-if="loading" class="spinner-border spinner-border-sm me-2"></span>
                            {{ loading ? 'Отправка...' : 'Заказать звонок' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
   // TODO: Авто открывать модалку только один раз, проверять по сессии
    document.addEventListener('DOMContentLoaded', function() {
        const fields = <?= $jsonFields ?>;
        const formId = <?= $formId ?>;
        const formHandler = '/local/ajax/callback_form.php';

        Vue.createApp({
            data() {
                return {
                    fields: fields,
                    loading: false,
                    submitError: '',
                    submitSuccess: '',
                    modal: null,
                    timeoutId: null,
                }
            },
            mounted() {
                this.modal = new bootstrap.Modal(document.getElementById('callbackModal'), {
                    backdrop: 'static',
                    keyboard: true,
                });

                this.timeoutId = setTimeout(() => {
                    this.openModal();
                }, 20000);

                document.getElementById('callbackModal').addEventListener('shown.bs.modal', () => {
                    if (this.timeoutId) {
                        clearTimeout(this.timeoutId);
                        this.timeoutId = null;
                    }
                    this.resetForm();
                });
            },
            beforeUnmount() {
                if (this.timeoutId) {
                    clearTimeout(this.timeoutId);
                }
            },
            methods: {
                openModal() {
                    this.resetForm();
                    this.modal.show();
                },
                resetForm() {
                    this.fields.forEach(field => {
                        field.value = '';
                        field.errors = [];
                        if (field.type === 'checkbox') {
                            field.checked = false;
                        }
                    });
                    this.submitError = '';
                    this.submitSuccess = '';
                    this.loading = false;
                },
                validateField(field) {
                    field.errors = [];

                    if (field.required) {
                        if (field.type === 'checkbox') {
                            if (!field.checked) {
                                field.errors.push('Необходимо согласие на обработку данных');
                            }
                        } else {
                            if (!field.value || field.value.trim() === '') {
                                field.errors.push('Поле обязательно для заполнения');
                            }
                        }
                    }

                    // TODO: Придумать что-то получше
                    if (field.sid === 'SIMPLE_QUESTION_295' && field.value) {
                        const phoneRegex = /^[+\d\s\-()]{10,20}$/;
                        if (!phoneRegex.test(field.value)) {
                            field.errors.push('Введите корректный номер телефона');
                        }
                    }

                    return field.errors.length === 0;
                },
                validateAll() {
                    let isValid = true;
                    this.fields.forEach(field => {
                        if (!this.validateField(field)) {
                            isValid = false;
                        }
                    });
                    return isValid;
                },
                async submitForm() {
                    if (!this.validateAll()) {
                        this.submitError = 'Пожалуйста, исправьте ошибки в форме';
                        const firstError = this.fields.find(f => f.errors.length > 0);
                        if (firstError) {
                            const el = document.getElementById('field_' + firstError.id);
                            if (el) el.focus();
                        }
                        return;
                    }

                    this.loading = true;
                    this.submitError = '';
                    this.submitSuccess = '';

                    try {
                        const formData = new FormData();
                        formData.append('WEB_FORM_ID', formId);

                        this.fields.forEach(field => {
                            if (field.type === 'checkbox') {
                                formData.append('form_checkbox_' + field.sid +'[]', field.checked ? field.id : '');
                            } else {
                                formData.append('form_text_' + field.id, field.value || '');
                            }
                        });

                        const response = await fetch(formHandler, {
                            method: 'POST',
                            body: formData,
                        });

                        const result = await response.json();

                        if (result.success === true) {
                            this.submitSuccess = 'Спасибо! Мы свяжемся с вами в ближайшее время.';
                            setTimeout(() => {
                                this.modal.hide();
                            }, 3500);
                        } else {
                            this.submitError = result.message || 'Произошла ошибка при отправке';
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        this.submitError = 'Произошла ошибка при отправке. Попробуйте позже.';
                    } finally {
                        this.loading = false;
                    }
                }
            }
        }).mount('#callbackApp');
    });
</script>

