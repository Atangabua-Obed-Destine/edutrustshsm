@extends('layouts.admin')

@section('title', __('Add Expense'))
@section('breadcrumb', __('Income & Expense > Expense > Add'))

@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h3 class="text-lg font-semibold text-gray-700 mb-6">{{ __('Add Expense') }}</h3>
    @include('admin.account.expense._form', ['action' => route('admin.account.expense.store'), 'method' => 'POST'])
</div>
@endsection
