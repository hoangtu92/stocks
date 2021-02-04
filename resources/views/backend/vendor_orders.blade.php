@extends("app")

@section("content")
<div style="display: flex; align-items: center; justify-content: center; flex-direction: column; width: 100%">


    @if(count($openDeal) > 0)
    <h1>未實現損益</h1>
    <table border='1' cellpadding='5' cellspacing='0'>
        <thead>
        <tr style="background-color: rgba(113,179,252,0.3)">

            <th>日期<br><small>Date</small></th>
            <th>Open Time<br><small>Open Time</small></th>
            <th>股名<br><small>Stock</small></th>
            <th>張數<br><small>Qty</small></th>
            <th>成本<br><small>Price</small></th>
            <th>現價<br><small>current_price</small></th>
            {{--<th>損益<br><small>current_profit</small></th>
            <th>獲利率<br><small>current_profit_percent</small></th>--}}
            <th>Type<br><small>Type</small></th>
            <th>Order_type<br><small>Order_type</small></th>
            {{--<th>Action</th>--}}

        </tr>
        </thead>
        <tbody>
        @foreach($openDeal as $tr)
        <tr style="background-color: rgba(121,252,0,0.05)">

            <td>{{$tr->date}}</td>
            <td>{{$tr->time}}</td>
            <td><a style="color: deepskyblue" title="View stock chart" href="{{ route("stock_chart", ["date" => $tr->date, "code" => $tr->stock->code]) }}" target="_blank">{{$tr->stock->code}}- {{$tr->stock->name}}</a></td>
            <td>{{$tr->qty}}</td>
            <td>{{$tr->price}}</td>
            <td>{{$tr->price}}</td>
            {{--<td @if($tr->current_profit <= 0) style="color: green" @else style="color: red" @endif>{{$tr->profit}}</td>
            <td @if($tr->current_profit_percent <= 0) style="color: green" @else style="color: red" @endif>{{$tr->current_profit_percent}}%</td>--}}
            <td>{{$tr->type}}</td>
            <td>{{$tr->order_type}}</td>
            {{--<td><form method="post" action="{{ route("close_order") }}">
                @csrf
                <input type="hidden" name="order_id" value="{{ $tr->order_id  }}">
                <input class="red-btn" type="submit" value="Close">
                </form> </td>--}}

        </tr>
        @endforeach

        {{--<tr>
            <td colspan="12" style="border: none">
                <form method="post" action="{{ route("close_all_orders") }}">
                @csrf
                <p class="server" style="text-align: right"><input class="red-btn" type="submit" value="Close all orders"></p>
                </form>
            </td>
        </tr>--}}

        </tbody>
    </table>

    <hr>
    @endif

</div>

<script>
    setInterval(function () {
        //window.location.reload();
    }, 120000);

    window.onload = function(){
        var total = 0;
        document.querySelectorAll('table#closeDeal tbody tr').forEach(function(o, j){
            o.querySelectorAll('td').forEach(function(td, i){
                if(i === 8) total += parseFloat(td.innerText)
            })
        });

        sum.innerText = "Total: " + Math.round(total);

    }
</script>
@endsection
