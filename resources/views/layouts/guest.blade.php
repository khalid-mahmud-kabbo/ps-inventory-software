<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $appDirection }}">
    <head>
    <meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<!--favicon-->
	<link rel="icon" href='{{ url("/fevicon/" . $fevicon) }}'  type="image/png" />
    <title>@yield('title', app('company')['name'])</title>
    <link rel="stylesheet" href="{{ versionedAsset('custom/libraries/iziToast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="{{ versionedAsset('custom/libraries/flatpickr/flatpickr.min.css') }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="src/assets/vendors/font-awesome/css/font-awesome.min.css" rel="stylesheet">
    <link href="src/assets/vendors/fontawesome-free-6.6.0-web/css/all.min.css" rel="stylesheet">
    <style>
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear,
        input[type="password"]::-webkit-contacts-auto-fill-button,
        input[type="password"]::-webkit-credentials-auto-fill-button {
            display: none;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        :root {
            --primary-100: #008080;
            --primary-200: #112441;
            --accent: #248383;
            --text: #2d2d2d;
        }

        body {
            min-height: 100vh;
            display: flex;
            background: #f8f9fa;
            padding: 1rem;
        }

        .container {
            width: 800px;
            height: 500px;
            margin: auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            display: flex;
            border: 1px solid rgba(0, 128, 128, 0.1);
        }

        .left-panel {
            flex: 0.5;
            background: linear-gradient(135deg, var(--primary-100), var(--primary-200));
            padding: 2rem;
            border-radius: 12px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: center;
        }

        .logo-container {
            width: 100px;
            height: 100px;
            background: white;
            border-radius: 50%;
            margin: 0 auto 1.5rem;
            overflow: hidden;
            position: relative;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            padding: 8px;
        }

        .logo-container svg {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 100%;
            padding: 8px;
        }

        .right-panel {
            flex: 0.4;
            padding: 2rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .form-title {
            text-align: center;
            margin-bottom: 2rem;
            color: var(--primary-100);
            font-size: 1.5rem;
            font-weight: 600;
        }

        .form-container {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
        }

        .login-form {
            display: flex;
            flex-direction: column;
            gap: 0;
            margin-left: 25px;
            width: 100%;
        }

        .input-group {
            margin-bottom: 1.25rem;
            position: relative;
        }

        .input-group input {
            width: 100%;
            padding: 0.75rem 2.5rem 0.75rem 0.75rem;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            font-size: 0.9rem;
            transition: 0.2s;
        }

        .input-group input:focus {
            border-color: var(--primary-100);
            box-shadow: 0 0 0 3px rgba(0, 128, 128, 0.1);
            outline: none;
        }

        .input-group label {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 0.9rem;
            pointer-events: none;
            transition: 0.2s;
            background: white;
            padding: 0 0.25rem;
        }

        .input-group input:focus~label,
        .input-group input:valid~label {
            top: 0;
            font-size: 0.75rem;
            color: var(--primary-100);
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            padding: 5px;
            margin-top: 2px;
            background: transparent;
            border: none;
        }

        .password-toggle svg {
            width: 20px;
            height: 20px;
            fill: #666;
            transition: fill 0.2s;
        }

        .password-toggle:hover svg {
            fill: var(--primary-100);
        }

        .submit-btn {
            width: 100%;
            padding: 0.75rem;
            background: var(--primary-100);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: 0.2s;
            margin-top: 1rem;
        }

        .submit-btn:hover {
            background: var(--primary-200);
        }

        @media (max-width: 768px) {
            .container {
                width: 100%;
                height: auto;
                flex-direction: column;
            }

            .left-panel {
                padding: 1.5rem;
                display: none;
            }

            .right-panel {
                padding: 2rem 1rem;
            }
        }
    </style>
</head>

    <body class="">
        @yield('container')

        @include('layouts.script')
    </body>


</html>
