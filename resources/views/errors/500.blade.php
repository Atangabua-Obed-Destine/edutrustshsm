@extends('errors.layout')

@section('code', '500')
@section('title', __('Something went wrong'))
{{-- Never surface the exception message here: it can leak internals. --}}
@section('message', __('An unexpected error occurred. Please try again, or contact administration if it persists.'))
