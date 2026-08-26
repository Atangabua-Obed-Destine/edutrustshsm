@extends('layouts.admin')

@section('title', __('Edit Expense'))
@section('breadcrumb', __('Income & Expense > Expense > Edit'))

@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h3 class="text-lg font-semibold text-gray-700 mb-6">{{ __('Edit Expense') }}</h3>
    @include('admin.account.expense._form', ['action' => route('admin.account.expense.update', $expense), 'method' => 'PUT'])
</div>
@endsection
