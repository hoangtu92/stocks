<style>
    .level-3 {
        background-color: red;
    }

    .level-2 {
        background-color: yellow;
    }

    .level-1 {

    }
    th{
        text-transform: uppercase;
    }
    small{
        font-size: 9px
    }
    select{
        height: 30px;
        padding: 5px 20px;
    }
    label{
        width: 100%;
        display: block;
    }
</style>
<h1>未實現損益</h1>

<table border='1' cellpadding='5' cellspacing='0'>
    <thead>
    <tr style="background-color: rgba(113,179,252,0.3)">

        @foreach($header as $key => $value)
            <th>{{$value}}<br><small>{{$key}}</small></th>
        @endforeach

    </tr>
    </thead>
    <tbody>
    @if(count($openDeal) > 0)
        @foreach($openDeal as $tr)
            <tr style="background-color: rgba(121,252,0,0.05)">

                @foreach($tr as $key=> $td)
                    @if(!isset($header[$key])) @continue @endif
                    @if(is_numeric($td) && preg_match("/profit/", $key))
                            @if($td <= 0)
                                <td style="color: green">{{$td}}@if(preg_match("/range|rate|percent/", $key))%@endif</td>
                                @else
                                <td style="color: red">{{$td}}@if(preg_match("/range|rate|percent/", $key))%@endif</td>
                                @endif
                        @else
                            <td>{{$td}}@if(preg_match("/range|rate|percent/", $key))%@endif</td>
                        @endif

                @endforeach


            </tr>
        @endforeach
    @endif
    </tbody>
</table>

<hr>
<h1>已實現損益
</h1>

<table border='1' cellpadding='5' cellspacing='0'>
    <thead>
    <tr style="background-color: rgba(113,179,252,0.3)">

        @foreach($header2 as $key => $value)
            <th>{{$value}}<br><small>{{$key}}</small></th>
        @endforeach

    </tr>
    </thead>
    <tbody>
    @if(count($closeDeal) > 0)
        @foreach($closeDeal as $tr)
            <tr style="background-color: rgba(121,252,0,0.05)">

                    <td>{{$tr->date}}</td>
                    <td>{{$tr->open_time}}</td>
                    <td>{{$tr->close_time}}</td>
                    <td>{{$tr->stock}}</td>
                    <td>{{$tr->qty}}</td>
                    @if($tr->type == 'SELL')
                        <td>{{$tr->first_price}}</td>
                        <td>{{$tr->second_price}}</td>
                    @else
                        <td>{{$tr->second_price}}</td>
                        <td>{{$tr->first_price}}</td>
                    @endif
                    <td @if($tr->profit <= 0)  style="color: green" @else  style="color: red" @endif>{{$tr->profit}}</td>
                    <td @if($tr->profit_percent <= 0)  style="color: green" @else  style="color: red" @endif>{{$tr->profit_percent}}%</td>
                    <td>{{$tr->fee}}</td>
                    <td>{{$tr->tax}}</td>
                    <td>{{$tr->type}}</td>


            </tr>
        @endforeach
    @endif
    </tbody>
</table>


<script>
    setInterval(function () {
        window.location.reload();
    }, 5000)
</script>

