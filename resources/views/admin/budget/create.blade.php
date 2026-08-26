@extends('layouts.admin')

@section('title', __('Create Budget'))
@section('breadcrumb', __('Budgets > Create'))

@section('content')
<div class="max-w-3xl bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h3 class="text-lg font-semibold text-gray-700 mb-6">{{ __('Create Budget') }}</h3>
    @include('admin.budget._form', ['action' => route('admin.budget.store'), 'method' => 'POST'])
</div>
@endsection
