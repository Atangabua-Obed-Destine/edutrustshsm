@php($colors = [
    'green' => 'bg-green-600 hover:bg-green-700',
    'amber' => 'bg-amber-500 hover:bg-amber-600',
    'slate' => 'bg-slate-600 hover:bg-slate-700',
    'red' => 'bg-red-500 hover:bg-red-600',
    'blue' => 'bg-blue-500 hover:bg-blue-600',
])
<form method="POST" action="{{ route('admin.budget.' . $route, $budget) }}" class="inline"
      @if($confirm ?? false) onsubmit="return confirm('{{ __('Are you sure?') }}')" @endif>
    @csrf
    <button type="submit" class="{{ $colors[$color] ?? $colors['blue'] }} text-white px-3 py-1.5 rounded-lg text-sm">{{ $label }}</button>
</form>
