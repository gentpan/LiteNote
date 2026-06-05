@if(!empty($activitySummary))
    <section class="kami-summary" aria-label="今日动态摘要">
        <div class="kami-summary-main">
            <p>今日</p>
            <strong>{{ (int)($activitySummary['total_today'] ?? 0) }}</strong>
            <span>条记录 · {{ (int)($activitySummary['active_types'] ?? 0) }} 类活跃</span>
        </div>

        @if(!empty($activitySummary['metrics']))
            <div class="kami-summary-metrics">
                @foreach($activitySummary['metrics'] as $metric)
                    <span>
                        <i class="{{ $metric['icon'] }}"></i>
                        <b>{{ $metric['label'] }}</b>
                        <em>{{ $metric['value'] }}</em>
                    </span>
                @endforeach
            </div>
        @endif

        @if(!empty($activitySummary['recent']))
            <div class="kami-summary-recent">
                @foreach($activitySummary['recent'] as $item)
                    <a href="/activity#activity-{{ $item->id }}">
                        <time>{{ \App\Core\Helper::formatDate((string)$item->happened_at, 'H:i') }}</time>
                        <span>{{ $item->title }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
@endif
