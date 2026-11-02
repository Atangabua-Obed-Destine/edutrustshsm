{{--
    The questions a head teacher asks first thing: have the registers been
    taken, are this term's marks in, is the money coming in, and what is
    waiting for me. Each figure links to where it is acted on.
--}}
@php $g = $glance; @endphp
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
    @can('attendance.view')
    <a href="{{ route('admin.attendance.index') }}" class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:border-teal-300 transition">
        <p class="text-xs text-gray-500 uppercase tracking-wide">{{ __("Today's registers") }}</p>
        <p class="text-2xl font-bold mt-1 {{ $g['registers']['taken'] >= $g['registers']['classes'] && $g['registers']['classes'] > 0 ? 'text-emerald-600' : 'text-gray-800' }}">
            {{ $g['registers']['taken'] }}<span class="text-base text-gray-400 font-medium">/{{ $g['registers']['classes'] }}</span>
        </p>
        <p class="text-xs text-gray-500 mt-1">
            @if($g['registers']['missing']->isNotEmpty())
                {{ __('Not taken: :list', ['list' => $g['registers']['missing']->take(4)->join(', ').($g['registers']['missing']->count() > 4 ? '…' : '')]) }}
            @elseif($g['registers']['classes'] > 0)
                {{ __('Every class has its register.') }} · {{ __(':n absent', ['n' => $g['registers']['absent']]) }}
            @else
                {{ __('No class yet.') }}
            @endif
        </p>
    </a>
    @endcan

    @can('marks-entry.view')
    <a href="{{ $g['marks']['term'] ? route('admin.analytics.readiness', ['term_id' => $g['marks']['term']->id]) : route('admin.marks.index') }}" class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:border-teal-300 transition">
        <p class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Marks this term') }}</p>
        <p class="text-2xl font-bold text-gray-800 mt-1">{{ $g['marks']['percent'] }}%</p>
        <div class="mt-2 h-1.5 bg-gray-100 rounded-full overflow-hidden"><div class="h-1.5 rounded-full {{ $g['marks']['percent'] >= 100 ? 'bg-emerald-500' : 'bg-sky-500' }}" style="width: {{ min(100, $g['marks']['percent']) }}%"></div></div>
        <p class="text-xs text-gray-500 mt-1">{{ __(':done of :total subject-sequences approved', ['done' => $g['marks']['done'], 'total' => $g['marks']['expected']]) }}{{ $g['marks']['term'] ? ' · '.$g['marks']['term']->name : '' }}</p>
    </a>
    @endcan

    @can('fee-report.view')
    <a href="{{ route('admin.fee-reports.index') }}" class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:border-teal-300 transition">
        <p class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Fees collected this year') }}</p>
        <p class="text-2xl font-bold text-gray-800 mt-1">{{ $g['fees']['rate'] }}%</p>
        <div class="mt-2 h-1.5 bg-gray-100 rounded-full overflow-hidden"><div class="h-1.5 rounded-full bg-emerald-500" style="width: {{ min(100, $g['fees']['rate']) }}%"></div></div>
        <p class="text-xs text-gray-500 mt-1">{{ __(':paid of :billed · :today today', ['paid' => number_format($g['fees']['paid']), 'billed' => number_format($g['fees']['billed']), 'today' => number_format($g['fees']['today'])]) }}</p>
    </a>
    @endcan

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <p class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Waiting for you') }}</p>
        <ul class="mt-2 space-y-1.5 text-sm">
            @can('marks-entry.edit')
            <li class="flex items-center justify-between">
                <a href="{{ route('admin.exam-publishing.index') }}" class="text-gray-700 hover:text-teal-700">{{ __('Marks to approve') }}</a>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $g['waiting']['marks'] > 0 ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-500' }}">{{ $g['waiting']['marks'] }}</span>
            </li>
            @endcan
            @can('parent-portal.view')
            <li class="flex items-center justify-between">
                <a href="{{ route('admin.parent-payments.index') }}" class="text-gray-700 hover:text-teal-700">{{ __('Parent payments to check') }}</a>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $g['waiting']['parent_payments'] > 0 ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-500' }}">{{ $g['waiting']['parent_payments'] }}</span>
            </li>
            @endcan
            @can('admission.view')
            <li class="flex items-center justify-between">
                <a href="{{ route('admin.admissions.applications.index') }}" class="text-gray-700 hover:text-teal-700">{{ __('Applications to review') }}</a>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $g['waiting']['applications'] > 0 ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-500' }}">{{ $g['waiting']['applications'] }}</span>
            </li>
            @endcan
        </ul>
    </div>
</div>
