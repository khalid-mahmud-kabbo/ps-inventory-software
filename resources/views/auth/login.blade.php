@extends('layouts.guest')
@section('title', __('auth.login'))

@section('container')

<div class="container">
        <div class="left-panel">
            <div class="logo-container">
                <img id="sidebar-logo" src={{ url("/app/getimage/" . app('site')['colored_logo']) }} alt="Company Logo">
            </div>
            <h2 style="margin-bottom: 1rem">{{ __('app.welcome-msg') }}</h2>
            <p style="font-size: 0.9rem; opacity: 0.9">{{ __('app.welcome-msg-desc') }}</p>
        </div>
        <div class="right-panel">
            <div class="form-container">
                <h2 class="form-title">{{ __('app.sign_in') }}</h2>
                @include('layouts.session')
                    <form class="login-form" id="loginForm" action="{{ route('login') }}" enctype="multipart/form-data">
                        @csrf
                        @method('POST')

                    <div class="input-group">
                        <input type="email" name="email" id="email" autocomplete="off" required>
                        <label>{{ __('app.email') }}</label>
                    </div>
                    <div class="input-group">
                        <input type="password" name="password" id="password" autocomplete="off" required>
                        <label>{{ __('app.password') }}</label>
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <svg viewBox="0 0 24 24">
                                <path
                                    d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z" />
                            </svg>
                        </button>
                    </div>
                    <button type="submit" class="submit-btn" id="submit-btn">{{ __('app.sign_in') }}</button>
                </form>
            </div>
        </div>
    </div>

@endsection

@section('js')
<script src="custom/js/login.js"></script>
@endsection
