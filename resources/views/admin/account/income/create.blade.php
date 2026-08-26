@extends('layouts.admin')

@section('title', __('Add Income'))
@section('breadcrumb', __('Income & Expense > Income > Add'))

@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h3 class="text-lg font-semibold text-gray-700 mb-6">{{ __('Add Income') }}</h3>
    @include('admin.account.income._form', ['action' => route('admin.account.income.store'), 'method' => 'POST'])
</div>
@endsection
