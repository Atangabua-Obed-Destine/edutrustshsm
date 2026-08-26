@extends('layouts.admin')
@section('title', __('Add Staff'))
@section('breadcrumb', __('Human Resources > Staff > Add'))
@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h3 class="text-lg font-semibold text-gray-700 mb-6">{{ __('Add Staff') }}</h3>
    @include('admin.hr.staff._form', ['action' => route('admin.staff.store'), 'method' => 'POST'])
</div>
@endsection
