@extends('layouts.guest')

@section('page_title', 'Login')

@section('content')
<div class="login-container mt-4">
    <div class="login-header">
        <h1>Dashboard Builder</h1>
        <p>AI-Powered Dashboard Platform</p>
    </div>

    <div class="login-form">
        @if($errors->any())
            <div class="alert alert-danger">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="remember"> Remember me
                </label>
            </div>
            <button type="submit" class="btn-login" style="width:100%">Login</button>
        </form>
    </div>
</div>
@endsection
