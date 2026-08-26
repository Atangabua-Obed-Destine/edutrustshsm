@extends('layouts.admin')
@section('title', __('Edit Tax Group'))
@section('breadcrumb', __('Human Resources > Settings > Tax Groups > Edit'))
@section('content')
<div class="max-w-3xl bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h3 class="text-lg font-semibold text-gray-700 mb-6">{{ __('Edit Tax Group') }}: {{ $group->title }}</h3>
    @include('admin.hr.tax.groups._form', ['action' => route('admin.tax-groups.update', $group), 'method' => 'PUT'])
</div>
@endsection
