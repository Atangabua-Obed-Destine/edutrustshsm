@if(session('success'))
<div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
    <p class="text-sm text-emerald-700 font-medium">{{ session('success') }}</p>
</div>
@endif

@if(session('error'))
<div class="bg-red-50 border border-red-200 rounded-xl p-4">
    <p class="text-sm text-red-700 font-medium">{{ session('error') }}</p>
</div>
@endif

@if($errors->any())
<div class="bg-red-50 border border-red-200 rounded-xl p-4">
    <ul class="text-sm text-red-700 space-y-1 list-disc list-inside">
        @foreach($errors->all() as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
</div>
@endif
