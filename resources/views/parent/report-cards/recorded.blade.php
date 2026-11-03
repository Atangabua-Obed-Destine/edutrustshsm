@extends('parent.layouts.app')
@section('title', __('Record of results'))
@section('heading', __('Record of results'))

@section('content')
<div style="max-width: 900px;">
    <p style="font-size:0.84rem; color:#64748b; margin-bottom:12px;">{{ __('This year was before the school used this system. The results are copied from the school\'s report cards and checked against them.') }}</p>
    @include('admin.past-years._record')
    <div style="margin-top:14px;">
        <button type="button" onclick="window.print()" class="pp-btn" style="padding:8px 16px; border-radius:8px; background:#0f766e; color:#fff; font-size:0.85rem; border:0; cursor:pointer;">{{ __('Print') }}</button>
    </div>
</div>
@endsection
