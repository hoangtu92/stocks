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
<table border='1' cellpadding='5' cellspacing='0'>
    <thead>
    <tr>

        @foreach($header as $key => $value)

        <th>{{$value}}<br><small>{{$key}}</small></th>
        @endforeach

    </tr>
    </thead>
    <tbody>
    @foreach($data as $tr)
    <tr>

        {{--@foreach($tr as $key=> $td)
            @if(!isset($header[$key])) @continue @endif
        <td>{{$td}}@if(preg_match("/range|rate/", $key))%@endif</td>
        @endforeach--}}

        <td>{{$tr->date}}</td>
        <td>{{$tr->code}}</td>
        <td>{{$tr->name}}</td>
        <td>{{$tr->final}}</td>
        <td>{{$tr->range}}%</td>
        <td>{{$tr->vol}}</td>

        <td>{{$tr->agency}}</td>
        <td>{{$tr->total_agency_vol}}</td>
        <td>{{$tr->total_agency_rate}}</td>
        <td>{{$tr->single_agency_vol}}</td>
        <td>{{$tr->single_agency_rate}}</td>
        <td>{{$tr->agency_price}}</td>

        <td>{{$tr->large_trade}}</td>
        <td>{{$tr->trend}}</td>
        <td>{{$tr->place_order}}</td>
        <td>{{$tr->agency_forecast}}</td>

        <td>{{$tr->order_start}}</td>
        <td>{{$tr->max}}</td>
        <td>{{$tr->lowest}}</td>
        <td>{{$tr->arav_final}}</td>
        <td>{{$tr->order_price_range}}</td>
        <td>{{$tr->price_907}}</td>



    </tr>
    @endforeach

    </tbody>
</table>


