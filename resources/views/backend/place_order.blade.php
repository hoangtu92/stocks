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
                    <th>成本<br><small>Sell</small></th>
                    <th>現價<br><small>current_price</small></th>
                    <th>損益<br><small>current_profit</small></th>
                    <th>獲利率<br><small>current_profit_percent</small></th>
                    <th>Order_type<br><small>Order_type</small></th>
                    <th>Action</th>

                </tr>
                </thead>
                <tbody>
                @foreach($openDeal as $tr)
                    <tr style="background-color: rgba(121,252,0,0.05)">

                        <td>{{$tr->date}}</td>
                        <td>{{$tr->open_time}}</td>
                        <td>{{$tr->stock->code}}- {{$tr->stock->name}}</td>
                        <td>{{$tr->qty}}</td>
                        <td>{{$tr->sell}}</td>
                        <td>{{$tr->current_price}}</td>
                        <td @if($tr->current_profit <= 0) style="color: green" @else style="color: red" @endif>{{$tr->profit}}</td>
                        <td @if($tr->current_profit_percent <= 0) style="color: green" @else style="color: red" @endif>{{$tr->current_profit_percent}}%</td>
                        <td>{{$tr->order_type}}</td>
                        <td><form method="post" action="{{ route("close_order") }}">
                                @csrf
                                <input type="hidden" name="order_id" value="{{ $tr->order_id  }}">
                                <input class="red-btn" type="submit" value="Close">
                            </form> </td>

                    </tr>
                @endforeach

                <tr>
                    <td colspan="12" style="border: none">
                        <form method="post" action="{{ route("close_all_orders") }}">
                            @csrf
                            <p class="server" style="text-align: right"><input class="red-btn" type="submit" value="Close all orders"></p>
                        </form>
                    </td>
                </tr>

                </tbody>
            </table>

            <hr>
        @endif
        <h1>已實現損益</h1>

        <table border='1' cellpadding='5' cellspacing='0' id="closeDeal">
            <thead>
            <tr style="background-color: rgba(113,179,252,0.3)">

                <th>id<br><small>ID</small></th>
                <th>date<br><small>日期</small></th>
                <th>Open Time<br><small>Open Time</small></th>
                <th>Close Time<br><small>Close Time</small></th>
                <th>Stock<br><small>股名</small></th>
                <th>Qty<br><small>張數</small></th>
                <th>Buy<br><small>買入</small></th>
                <th>Sell<br><small>賣出</small></th>
                <th>Profit<br><small>損益</small></th>
                <th>Profit percent<br><small>獲利率</small></th>
                <th>Fee<br><small>手續費</small></th>
                <th>Tax<br><small>交易稅</small></th>
                <th>Order_type<br><small>Order_type</small></th>
            </tr>
            </thead>
            <tbody>
            @if(count($closeDeal) > 0)
                @foreach($closeDeal as $tr)
                    <tr style="background-color: rgba(121,252,0,0.05)">

                        <td>{{$tr->id}}</td>
                        <td>{{$tr->date}}</td>
                        <td>{{$tr->open_time}}</td>
                        <td>{{$tr->close_time}}</td>
                        <td><a style="color: deepskyblue" title="View stock chart" href="{{ route("stock_chart", ["date" => $tr->date, "code" => $tr->stock->code]) }}" target="_blank">{{$tr->stock->code}}- {{$tr->stock->name}}</a> </td>
                        <td>{{$tr->qty}}</td>
                        <td>{{$tr->buy}}</td>
                        <td>{{$tr->sell}}</td>
                        <td @if($tr->profit <= 0)  style="color: green"
                            @else  style="color: red" @endif>{{$tr->profit}}</td>
                        <td @if($tr->profit_percent <= 0)  style="color: green"
                            @else  style="color: red" @endif>{{$tr->profit_percent}}%
                        </td>
                        <td>{{$tr->fee}}</td>
                        <td>{{$tr->tax}}</td>
                        <td>{{$tr->order_type}}</td>


                    </tr>
                @endforeach
                <tr>
                    <td colspan="8"></td>
                    <td id="sum"></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            @endif
            </tbody>
        </table>
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
