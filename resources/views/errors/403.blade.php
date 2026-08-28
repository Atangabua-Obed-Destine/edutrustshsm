@extends('errors.layout')

@section('code', '403')
@section('title', __('Access denied'))
{{-- 403 messages are ours (CheckRole / CheckPermission), so they are safe to show. --}}
@section('message', ($exception ?? null)?->getMessage() ?: __('You do not have permission to view this page.'))
