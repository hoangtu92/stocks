@extends("app")

@section("content")
    <div style="display: flex; align-items: center; justify-content: center; flex-direction: column; width: 100%">
        <form method="get" action="{{ route("test") }}">
            @csrf
            <input type="date" name="filter_date" value="{{ $filter_date }}"
                   style="width: 200px; height: 30px; padding: 5px 10px" onchange="this.form.submit()">
        </form>

        @if(count($openDeal) > 0)
            <h1>未實現損益</h1>
            <table border='1' cellpadding='5' cellspacing='0'>
                <thead>
                <tr style="background-color: rgba(113,179,252,0.3)">

                    @foreach($header as $key => $value)
                        <th>{{$value}}<br><small>{{$key}}</small></th>
                    @endforeach

                    <th>Action</th>

                </tr>
                </thead>
                <tbody>
                @foreach($openDeal as $tr)
                    <tr style="background-color: rgba(121,252,0,0.05)">

                        @foreach($tr as $key=> $td)
                            @if(!isset($header[$key])) @continue @endif
                            @if(is_numeric($td) && preg_match("/profit/", $key))
                                @if($td <= 0)
                                    <td style="color: green">{{$td}}@if(preg_match("/range|rate|percent/", $key))
                                            %@endif</td>
                                @else
                                    <td style="color: red">{{$td}}@if(preg_match("/range|rate|percent/", $key))
                                            %@endif</td>
                                @endif
                            @else
                                <td>{{$td}}@if(preg_match("/range|rate|percent/", $key))%@endif</td>
                            @endif

                        @endforeach

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

                        <td>{{$tr->id}}</td>
                        <td>{{$tr->date}}</td>
                        <td>{{$tr->open_time}}</td>
                        <td>{{$tr->close_time}}</td>
                        <td>{{$tr->stock}}</td>
                        <td>{{$tr->qty}}</td>
                        <td>{{$tr->second_price}}</td>
                        <td>{{$tr->first_price}}</td>
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
            @endif
            </tbody>
        </table>
    </div>

    <script>
        setInterval(function () {
            window.location.reload();
        }, 120000)
    </script>
@endsection
