@extends('layouts.admin')
@section('title', __('Add Account'))
@section('breadcrumb', __('Accounting > Chart of Accounts > Add'))
@section('content')
<div class="max-w-3xl bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h3 class="text-lg font-semibold text-gray-700 mb-6">{{ __('Add Account') }}</h3>
    @include('admin.accounting.chart._form', ['action' => route('admin.chart-of-accounts.store'), 'method' => 'POST'])
</div>
@endsection
