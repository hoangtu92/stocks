@extends("app")

@section("content")

<form id="update" action="{{route("re_crawl_agency")}}" method="get">
    @csrf

    <input type="hidden" name="date" value="{{$filter_date}}">
<table border='1' cellpadding='5' cellspacing='0'>
    <thead>
    <tr>

        @foreach($data[array_keys($data)[0]] as $key => $value)
            @if(!isset($header[$key])) @continue @endif
        <th>{{$header[$key]}}<br><small>{{$key}}</small></th>
        @endforeach
       {{-- <th>Ticket</th>
        --}}

    </tr>
    </thead>
    <tbody>
    @foreach($data as $tr)
    <tr class="{{"level-{$tr->appearance}"}}">

        @foreach($tr as $key=> $td)
            @if(!isset($header[$key])) @continue @endif
        <td>{{$td}}@if(preg_match("/range|rate/", $key))%@endif</td>
        @endforeach

       {{-- <td><select name="borrow_ticket[{{$tr->code}}]">
                <option value="0" @if($tr->borrow_ticket == 0) selected @endif>0</option>
                <option value="1" @if($tr->borrow_ticket == 1) selected @endif>1</option>
            </select> </td>--}}



    </tr>
    @endforeach

    </tbody>
</table>
    <p style=" margin-top: 20px">
        {{--<input type="submit" value="Save" style="padding: 5px 10px; float: right;">--}}
        <input class="blue-btn"  type="submit" value="ReCrawl missing agency" style="float: right">
    </p>

</form>

@endsection
