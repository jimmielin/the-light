<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>封禁记录公示</title>
    <link rel="stylesheet" type="text/css" href="{{ asset('css/bootstrap.min.css') }}">
</head>

<body>
    <nav class="navbar navbar-expand-md navbar-dark bg-primary mb-2">
        <a class="navbar-brand" href="https://example.com">-removed-</a>
    </nav>
    <main role="main" class="container">
        <h1>封禁记录公示栏</h1>
        <div class="alert alert-warning">
            请注意：内测期间树洞规则正在公开反馈实验期当中，因此执行标准可能会有经常的变化。欢迎前往内测反馈表或发布树洞提出建议和意见，包括如何做得更好。<br>
            <b>我们相信透明治理和信息公开是最好的保护社区的方法。</b> 请勿将此内容传播至社区以外的用户。
        </div>
        <div class="alert alert-primary">树洞封禁记录仅公开显示系统判定的封禁理由和时间。如果您对某次封禁有异议，请联系管理团队调阅相关封禁操作记录档案。</div>

        <hr>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>ID#</th>
                    <th>建立时间</th>
                    <th>封禁至</th>
                    <th>判定理由</th>
                </tr>
            </thead>
            <tbody>
        @foreach($bDatas as $bData)
            <tr>
                <td>
                    @if($bData->post_id != null)
                    树洞#{{$bData->post_id}}
                    @else
                    树洞#{{$bData->comment->post_id}}
                    <br>
                    评论#{{$bData->comment->sequence}}
                    @endif
                </td>
                <td>{{$bData->created_at}}</td>
                <td>
                    {{$bData->until}}
                    @php
                    $tz = \Carbon\Carbon::parse($bData->until);
                    @endphp
                    @if($tz->isAfter(now()))
                    <br><span class='text-warning'>剩余时间约 {{$tz->diffInRealHours(now())}} 小时</span>
                    @endif
                </td>
                <td><pre>{{$bData->verdict}}</pre></td>
            </tr>
        @endforeach
            </tbody>
        </table>

        <hr>

        <div class="text-center">
            <a href="{{ url('/rules') }}">-removed- Rules</a> &middot;
            <a href="https://example.com">回到社区主页</a>
        </div>
    </main>
</body>

</html>
