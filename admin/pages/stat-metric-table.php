@if(empty($rows))
    <p class="empty">暂无数据</p>
@else
    <table class="admin-table">
        <thead>
            <tr><th>{{ $nameLabel ?? '名称' }}</th><th>访客</th></tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    <td>{{ $row['x'] ?? $row['name'] ?? '(none)' }}</td>
                    <td>{{ (int)($row['y'] ?? $row['visitors'] ?? 0) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
