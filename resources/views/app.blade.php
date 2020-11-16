<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Laravel</title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,600" rel="stylesheet">

    <!-- Styles -->
    <style>
        html, body {
            background-color: #fff;
            color: #636b6f;
            font-family: 'Nunito', sans-serif;
            font-weight: 200;
            height: 100vh;
            margin: 0;

        }

        .full-height {
            height: 100vh;
        }

        .flex-center {
            align-items: flex-start;
            display: flex;
            justify-content: flex-start;
            flex-direction: column;
        }

        .position-ref {
            position: relative;
        }

        .top-right {
            position: absolute;
            right: 10px;
            top: 18px;
        }

        .content {
            text-align: center;
        }

        .title {
            font-size: 84px;
        }

        .links > a {
            color: #636b6f;
            padding: 0 25px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: .1rem;
            text-decoration: none;
            text-transform: uppercase;
        }

        .m-b-md {
            margin-bottom: 30px;
        }

        table{
            margin-bottom: 30px
        }
    </style>

    <style>
        .level-3 {
            background-color: red;
        }

        .level-2 {
            background-color: yellow;
        }

        .level-1 {

        }

        th {
            text-transform: uppercase;
        }

        small {
            font-size: 9px
        }

        select {
            height: 30px;
            padding: 5px 20px;
        }

        label {
            width: 100%;
            display: block;
        }

        .menu {
            text-align: center;
            margin: 20px 0 30px 0;
        }

        .menu a{
            padding: 5px 10px;
            border: 1px solid rgba(0, 0, 0, 0.34);
            text-decoration: none;
        }
    </style>
</head>
<body>
<div class="menu">
    <a href="{{ route("test") }}">Auto trade</a>
    <a href="{{ route("order") }}">Order</a>
    <a href="{{ route("data") }}">Data</a>
    <a href="{{ route("general") }}">General</a>
</div>
<form method="post" action="{{ route("update_server_status") }}">
    @csrf
    <p class="server" style="text-align: center">
        <label>Server Status: <select name="server_status"
                       onchange="this.form.submit()">

                <option value="1" @if(Setting::get('server_status') == '1') selected @endif >Online</option>
                <option value="0" @if(Setting::get('server_status') == '0') selected @endif >Offline</option>
            </select></label>
    </p>
</form>
<div class="flex-center position-ref full-height">
    @yield("content")
</div>
</body>
</html>
