@extends('layouts.apply')
@section('title', __('Sign In'))

@section('content')
<div style="max-width: 420px; margin: 40px auto;">
    <div style="text-align: center; margin-bottom: 28px;">
        <h1 style="font-size: 1.5rem; font-weight: 700; color: #0f172a;">{{ __('Sign In') }}</h1>
        <p style="font-size: 0.85rem; color: #64748b; margin-top: 6px;">{{ __('Access your admission applications.') }}</p>
    </div>

    <div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); padding: 28px;">
        @if($errors->any())
        <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px; margin-bottom: 18px;">
            <ul style="list-style: none; padding: 0; margin: 0;">
                @foreach($errors->all() as $error)
                <li style="font-size: 0.8rem; color: #dc2626; padding: 2px 0;">• {{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="{{ route('apply.login.submit') }}">
            @csrf

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 0.78rem; font-weight: 600; color: #374151; margin-bottom: 5px;">{{ __('Email Address') }} <span style="color: #ef4444;">*</span></label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus
                       style="width: 100%; padding: 10px 12px; border: 1px solid {{ $errors->has('email') ? '#ef4444' : '#d1d5db' }}; border-radius: 7px; font-size: 0.85rem; outline: none; transition: border 0.2s;"
                       onfocus="this.style.borderColor='#0ea5e9'; this.style.boxShadow='0 0 0 3px rgba(14,165,233,0.1)'"
                       onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'"
                       placeholder="you@example.com">
            </div>

            <div style="margin-bottom: 22px;">
                <label style="display: block; font-size: 0.78rem; font-weight: 600; color: #374151; margin-bottom: 5px;">{{ __('Password') }} <span style="color: #ef4444;">*</span></label>
                <input type="password" name="password" required
                       style="width: 100%; padding: 10px 12px; border: 1px solid {{ $errors->has('password') ? '#ef4444' : '#d1d5db' }}; border-radius: 7px; font-size: 0.85rem; outline: none; transition: border 0.2s;"
                       onfocus="this.style.borderColor='#0ea5e9'; this.style.boxShadow='0 0 0 3px rgba(14,165,233,0.1)'"
                       onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'">
            </div>

            <button type="submit"
                    style="width: 100%; padding: 11px; background: #0ea5e9; color: #fff; border: none; border-radius: 8px; font-size: 0.88rem; font-weight: 600; cursor: pointer; transition: background 0.2s;"
                    onmouseover="this.style.background='#0284c7'" onmouseout="this.style.background='#0ea5e9'">
                {{ __('Sign In') }}
            </button>
        </form>
    </div>

    <p style="text-align: center; margin-top: 18px; font-size: 0.82rem; color: #64748b;">
        {{ __("Don't have an account?") }}
        <a href="{{ route('apply.register') }}" style="color: #0ea5e9; font-weight: 600;"
           onmouseover="this.style.color='#0284c7'" onmouseout="this.style.color='#0ea5e9'">{{ __('Register here') }}</a>
    </p>
</div>
@endsection
