<?php

declare(strict_types=1);

namespace Lalaz\Web\Tests\Unit\View\Form;

use Lalaz\Web\Tests\TestCase;
use Lalaz\Web\View\Form\FormBuilder;
use Lalaz\Web\Http\ViewDataBag;

class FormBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ViewDataBag::reset();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        ViewDataBag::reset();
        $_SESSION = [];
        parent::tearDown();
    }

    public function test_method_generates_hidden_field_with_method_value(): void
    {
        $html = FormBuilder::method('DELETE');

        $this->assertSame('<input type="hidden" name="_method" value="DELETE">', $html);
    }

    public function test_method_uppercases_method_name(): void
    {
        $html = FormBuilder::method('put');

        $this->assertSame('<input type="hidden" name="_method" value="PUT">', $html);
    }

    public function test_csrf_generates_hidden_field_with_token(): void
    {
        $html = FormBuilder::csrf();

        $this->assertStringContainsString('<input type="hidden" name="', $html);
        $this->assertStringContainsString('value="', $html);
    }

    public function test_open_generates_form_tag_with_action_and_method(): void
    {
        $html = FormBuilder::open('/users', 'POST');

        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('action="/users"', $html);
        $this->assertStringContainsString('method="POST"', $html);
    }

    public function test_open_includes_csrf_for_post_forms(): void
    {
        $html = FormBuilder::open('/users', 'POST');

        $this->assertStringContainsString('type="hidden"', $html);
    }

    public function test_open_excludes_csrf_for_get_forms(): void
    {
        $html = FormBuilder::open('/search', 'GET');

        $this->assertStringNotContainsString('type="hidden"', $html);
    }

    public function test_open_converts_put_to_post_with_method_field(): void
    {
        $html = FormBuilder::open('/users/5', 'PUT');

        $this->assertStringContainsString('method="POST"', $html);
        $this->assertStringContainsString('name="_method"', $html);
        $this->assertStringContainsString('value="PUT"', $html);
    }

    public function test_open_converts_delete_to_post_with_method_field(): void
    {
        $html = FormBuilder::open('/users/5', 'DELETE');

        $this->assertStringContainsString('method="POST"', $html);
        $this->assertStringContainsString('name="_method"', $html);
        $this->assertStringContainsString('value="DELETE"', $html);
    }

    public function test_open_accepts_additional_attributes(): void
    {
        $html = FormBuilder::open('/users', 'POST', ['class' => 'my-form', 'id' => 'user-form']);

        $this->assertStringContainsString('class="my-form"', $html);
        $this->assertStringContainsString('id="user-form"', $html);
    }

    public function test_close_generates_closing_form_tag(): void
    {
        $html = FormBuilder::close();

        $this->assertSame('</form>', $html);
    }

    public function test_text_generates_input_with_name_and_id(): void
    {
        $html = FormBuilder::text('username', ['wrapperClass' => '']);

        $this->assertStringContainsString('type="text"', $html);
        $this->assertStringContainsString('name="username"', $html);
        $this->assertStringContainsString('id="username"', $html);
    }

    public function test_text_includes_label_when_provided(): void
    {
        $html = FormBuilder::text('username', ['label' => 'Username', 'wrapperClass' => '']);

        $this->assertStringContainsString('<label for="username">Username', $html);
    }

    public function test_text_shows_required_indicator(): void
    {
        $html = FormBuilder::text('username', ['label' => 'Username', 'required' => true, 'wrapperClass' => '']);

        $this->assertStringContainsString('required', $html);
        $this->assertStringContainsString('*', $html);
    }

    public function test_text_uses_old_input_value(): void
    {
        $_SESSION['_old_input'] = ['username' => 'john'];
        ViewDataBag::reset();

        $html = FormBuilder::text('username', ['wrapperClass' => '']);

        $this->assertStringContainsString('value="john"', $html);
    }

    public function test_text_adds_error_class_when_field_has_errors(): void
    {
        $_SESSION['_errors'] = ['email' => ['Invalid email']];
        ViewDataBag::reset();

        $html = FormBuilder::text('email', ['wrapperClass' => '']);

        $this->assertStringContainsString('is-invalid', $html);
    }

    public function test_text_shows_error_message(): void
    {
        $_SESSION['_errors'] = ['email' => ['Invalid email']];
        ViewDataBag::reset();

        $html = FormBuilder::text('email', ['wrapperClass' => '']);

        $this->assertStringContainsString('Invalid email', $html);
        $this->assertStringContainsString('invalid-feedback', $html);
    }

    public function test_email_generates_email_input(): void
    {
        $html = FormBuilder::email('user_email', ['wrapperClass' => '']);

        $this->assertStringContainsString('type="email"', $html);
        $this->assertStringContainsString('name="user_email"', $html);
    }

    public function test_password_never_repopulates_value(): void
    {
        $_SESSION['_old_input'] = ['password' => 'secret123'];
        ViewDataBag::reset();

        $html = FormBuilder::password('password', ['wrapperClass' => '']);

        $this->assertStringContainsString('type="password"', $html);
        $this->assertStringContainsString('value=""', $html);
    }

    public function test_number_generates_number_input_with_min_max(): void
    {
        $html = FormBuilder::number('age', ['min' => 0, 'max' => 120, 'wrapperClass' => '']);

        $this->assertStringContainsString('type="number"', $html);
        $this->assertStringContainsString('min="0"', $html);
        $this->assertStringContainsString('max="120"', $html);
    }

    public function test_hidden_generates_hidden_input(): void
    {
        $html = FormBuilder::hidden('user_id', '123');

        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringContainsString('name="user_id"', $html);
        $this->assertStringContainsString('value="123"', $html);
    }

    public function test_textarea_generates_textarea_element(): void
    {
        $html = FormBuilder::textarea('bio', ['rows' => 5, 'wrapperClass' => '']);

        $this->assertStringContainsString('<textarea', $html);
        $this->assertStringContainsString('name="bio"', $html);
        $this->assertStringContainsString('rows="5"', $html);
        $this->assertStringContainsString('</textarea>', $html);
    }

    public function test_textarea_uses_old_input_value(): void
    {
        $_SESSION['_old_input'] = ['bio' => 'Hello world'];
        ViewDataBag::reset();

        $html = FormBuilder::textarea('bio', ['wrapperClass' => '']);

        $this->assertStringContainsString('>Hello world</textarea>', $html);
    }

    public function test_select_generates_select_with_options(): void
    {
        $options = ['br' => 'Brazil', 'us' => 'USA'];
        $html = FormBuilder::select('country', $options, ['wrapperClass' => '']);

        $this->assertStringContainsString('<select', $html);
        $this->assertStringContainsString('name="country"', $html);
        $this->assertStringContainsString('<option value="br">Brazil</option>', $html);
        $this->assertStringContainsString('<option value="us">USA</option>', $html);
    }

    public function test_select_includes_placeholder_option(): void
    {
        $options = ['br' => 'Brazil'];
        $html = FormBuilder::select('country', $options, ['placeholder' => 'Select...', 'wrapperClass' => '']);

        $this->assertStringContainsString('<option value="">Select...</option>', $html);
    }

    public function test_select_marks_old_input_as_selected(): void
    {
        $_SESSION['_old_input'] = ['country' => 'us'];
        ViewDataBag::reset();

        $options = ['br' => 'Brazil', 'us' => 'USA'];
        $html = FormBuilder::select('country', $options, ['wrapperClass' => '']);

        $this->assertStringContainsString('<option value="us" selected>USA</option>', $html);
    }

    public function test_checkbox_generates_checkbox_input(): void
    {
        $html = FormBuilder::checkbox('remember', ['label' => 'Remember me', 'wrapperClass' => '']);

        $this->assertStringContainsString('type="checkbox"', $html);
        $this->assertStringContainsString('name="remember"', $html);
        $this->assertStringContainsString('Remember me', $html);
    }

    public function test_checkbox_marks_as_checked_from_old_input(): void
    {
        $_SESSION['_old_input'] = ['newsletter' => '1'];
        ViewDataBag::reset();

        $html = FormBuilder::checkbox('newsletter', ['wrapperClass' => '']);

        $this->assertStringContainsString('checked', $html);
    }

    public function test_radio_generates_radio_input(): void
    {
        $html = FormBuilder::radio('gender', 'male', ['label' => 'Male', 'wrapperClass' => '']);

        $this->assertStringContainsString('type="radio"', $html);
        $this->assertStringContainsString('name="gender"', $html);
        $this->assertStringContainsString('value="male"', $html);
        $this->assertStringContainsString('Male', $html);
    }

    public function test_radio_marks_as_checked_when_matches_old_input(): void
    {
        $_SESSION['_old_input'] = ['gender' => 'female'];
        ViewDataBag::reset();

        $html = FormBuilder::radio('gender', 'female', ['wrapperClass' => '']);

        $this->assertStringContainsString('checked', $html);
    }

    public function test_submit_generates_submit_button(): void
    {
        $html = FormBuilder::submit('Save');

        $this->assertStringContainsString('<button', $html);
        $this->assertStringContainsString('type="submit"', $html);
        $this->assertStringContainsString('>Save</button>', $html);
    }

    public function test_submit_accepts_custom_class(): void
    {
        $html = FormBuilder::submit('Delete', ['class' => 'btn btn-danger']);

        $this->assertStringContainsString('class="btn btn-danger"', $html);
    }

    public function test_button_generates_button_element(): void
    {
        $html = FormBuilder::button('Cancel', ['type' => 'button']);

        $this->assertStringContainsString('<button', $html);
        $this->assertStringContainsString('type="button"', $html);
        $this->assertStringContainsString('>Cancel</button>', $html);
    }

    public function test_escapes_html_in_values(): void
    {
        $_SESSION['_old_input'] = ['name' => '<script>alert("xss")</script>'];
        ViewDataBag::reset();

        $html = FormBuilder::text('name', ['wrapperClass' => '']);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
