@extends('layouts.admin')

@section('title', __('Edit Budget'))
@section('breadcrumb', __('Budgets > Edit'))

@section('content')
<div class="max-w-3xl bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h3 class="text-lg font-semibold text-gray-700 mb-6">{{ __('Edit Budget') }}: {{ $budget->budget_code }}</h3>
    @include('admin.budget._form', ['action' => route('admin.budget.update', $budget), 'method' => 'PUT'])
</div>
@endsection
