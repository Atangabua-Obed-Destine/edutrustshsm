@extends('layouts.admin')

@section('title', __('Add Payment Account'))
@section('breadcrumb', __('Payment Accounts > Add'))

@section('content')
<div class="max-w-3xl bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h3 class="text-lg font-semibold text-gray-700 mb-6">{{ __('Add Payment Account') }}</h3>
    @include('admin.payment-account._form', ['action' => route('admin.payment-account.store'), 'method' => 'POST'])
</div>
@endsection
