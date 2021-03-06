<?php

// API

$app->bind("/api/forms/submit/:form", function($params) use($app){

    $form = $params["form"];

    // Security check

    if($formhash = $app->param("__csrf", false)) {

        if($formhash != $app->hash($form)) {
            return false;
        }

    } else {
        return false;
    }

    $frm = $app->data->common->forms->findOne(["name"=>$form]);

    if(!$frm) {
        return false;
    }

    if($formdata = $app->param("form", false)) {

        if(isset($frm["email"]) && filter_var($frm["email"], FILTER_VALIDATE_EMAIL)) {

            $body = array();

            foreach ($formdata as $key => $value) {
                $body[] = "<b>{$key}:</b>\n<br>";
                $body[] = (is_string($value) ? $value:json_encode($value))."\n<br>";
            }

            $app("mailer")->mail($frm["email"], $app->param("__mailsubject", "New form data for: ".$form), implode("\n<br>", $body));
        }

        if(isset($frm["entry"]) && $frm["entry"]) {

            $collection = "form".$frm["_id"];
            $entry      = ["data" => $formdata, "created"=>time()];
            $app->data->forms->{$collection}->insert($entry);
        }

        return json_encode($formdata);

    } else {
        return "false";
    }

});

$this->module("forms")->extend(array(

    "form" => function($name, $options) use($app) {

        $options = array_merge(array(
            "id"    => uniqid("form"),
            "class" => "",
            "csrf"  => $app->hash($name)
        ), $options);

        echo $app->view("forms:views/api/form.php", compact('name', 'options'));
    }
));


if (!function_exists('form')) {

    function form($name, $options=array()) {
        echo cockpit("forms")->form($name, $options);
    }
}

// ADMIN

if(COCKPIT_ADMIN) {

    $app->on("admin.init", function() use($app){

        if(!$app->module("auth")->hasaccess("Forms","manage")) return;

        $app->bindClass("Forms\\Controller\\Forms", "forms");
        $app->bindClass("Forms\\Controller\\Api", "api/forms");

        $app("admin")->menu("top", [
            "url"    => $app->routeUrl("/forms"),
            "label"  => '<i class="uk-icon-inbox"></i>',
            "title"  => $app("i18n")->get("Forms"),
            "active" => (strpos($app["route"], '/forms') === 0)
        ], 5);

        // handle global search request
        $app->on("cockpit.globalsearch", function($search, $list) use($app){
            
            foreach ($app->data->common->forms->find()->toArray() as $f) {
                if(stripos($f["name"], $search)!==false){
                    $list[] = [
                        "title" => '<i class="uk-icon-inbox"></i> '.$f["name"], 
                        "url"   => $app->routeUrl('/forms/form/'.$f["_id"])
                    ];
                }
            }
        });
    });

    $app->on("admin.dashboard", function() use($app){

        if(!$app->module("auth")->hasaccess("Forms","manage")) return;

        $title       = $app("i18n")->get("Forms");
        $badge       = $app->data->common->forms->count();
        $forms = $app->data->common->forms->find()->limit(3)->sort(["created"=>-1])->toArray();

        echo $app->view("forms:views/dashboard.php with cockpit:views/layouts/dashboard.widget.php", compact('title', 'badge', 'forms'));
    });

    // acl
    $app("acl")->addResource("Forms", ['manage']);
}