<?php namespace Components\ContactManager\Controllers;
/*
=================================================
Module Name     :   Contact Manager
Module Version  :   v0.1
Compatible CMS  :   v1.2
Site            :
Description     :
===================================================
*/
use App;
use Exception;
use Input;
use Mail;
use Redirect;
use Str;
use Validator;
use View;

use Components\ContactManager\Models\ContactCategory;
use Components\ContactManager\Models\ContactDetail;
use Components\ContactManager\Models\ContactEmail;
use Components\ContactManager\Controllers\Backend\ContactController as BackendController;

class PublicController extends BackendController {

    /**
     * Show all contacts in the specified contact category
     * @param $alias
     * @return
     */
    public function showCategory($alias)
    {
        $category = ContactCategory::with('contacts')
                                    ->whereAlias($alias)
                                    ->first();

        $this->layout->title = "Contact in {$category->name}";
        $this->layout->content = View::make("public.default.contact-categories")
            ->with('title', "Contact in {$category->name}")
            ->with('category', $category);
    }

    /**
     * Display the contact page for the specified alias
     * @param $alias
     * @return
     */
    public function showPublic($category, $alias)
    {
        $form = $this->getForm(18);
        $model_name = "Components\\ContactManager\\Models\\{$form['model']}";
        $contact = $model_name::whereAlias($alias)->first();

        if (!$contact) {
            App::abort(404);
        }

        $contact->location = json_decode($contact->location, true);

        $display_options = json_decode($contact->display_options, true);

        $fields = array_combine($form['field_names'], $form['fields']);

        // Display only the fields that are set to be displayed
        $fields = array_filter($fields, function($field) use($display_options, $contact) {
            if (isset($display_options[$field]) && $display_options[$field] == 1) {
                return true;
            } else {
                // Remove the field from the record, so that it won't be displayed
                // except for the alias
                if ($field != 'alias') {
                    unset($contact->{$field});
                    return false;
                }
            }
        });

        $this->layout->title = "Contact Page for {$contact->name}";
        $this->layout->content = View::make("public.default.contact-manager")
            ->with('title', "Contact Page for {$contact->name}")
            ->with('contact', $contact)
            ->with('fields', $fields);
    }

    public function sendMessage($alias)
    {
        $input = Input::all();

        $rules = array(
                'email'   => 'required|min:5|email',
                'name'    => 'required|alpha_spaces|min:3',
                'message' => 'required'
            );

        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
            return Redirect::back()
                                ->withErrors($validator)
                                ->withInput();
        }

        $form = $this->getForm(18);
        $model_name = "Components\\ContactManager\\Models\\{$form['model']}";
        $contact = $model_name::whereAlias($alias)->first();

        ContactEmail::create(array(
                'name'       => $input['name'],
                'email'      => $input['email'],
                'subject'    => $input['subject'],
                'message'    => $input['message'],
                'contact_id' => $contact->id
            ));

        $input['message_text'] = $input['message'];
        try {
            Mail::send('public.'.$this->current_theme.'.email', $input, function($email_message) use($input, $contact) {
                $email_message->from($input['email'], $input['name']);
                $email_message->to($contact->email, $contact->name)
                        ->subject($input['subject']);
            });
        } catch (Exception $e) {
            return Redirect::back()
                                ->withInput()
                                ->with('error_message', $e->getMessage());
        }

        return Redirect::back()
                            ->with('success_message', 'The mail was sent.');
    }

}