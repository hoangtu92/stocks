<!DOCTYPE HTML>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="robots" content="noindex">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stock chart</title>

    <style type="text/css">
        /* FULL SCREEN */

        .chart:-webkit-full-screen {
            width: 100%;
            height: 100%;
        }

        .chart:-moz-full-screen {
            width: 100%;
            height: 100%;
        }

        .chart:-ms-fullscreen {
            width: 100%;
            height: 100%;
        }

        .chart:fullscreen {
            width: 100%;
            height: 100%;
        }

        /* GENERAL */

        .chart {
            float: left;
            height: calc(100vh - 100px);
            position: relative;
            width: 100%;
        }

        .highcharts-draw-mode {
            cursor: crosshair;
        }

        .left {
            float: left;
        }

        .right,
        .highcharts-stocktools-toolbar li.right {
            float: right;
        }

        /* GUI */

        .highcharts-stocktools-toolbar {
            margin: 0px 0px 0px 10px;
            padding: 0px;
            width: calc(100% - 63px);
        }

        .highcharts-stocktools-toolbar li {
            background-color: #f7f7f7;
            background-repeat: no-repeat;
            cursor: pointer;
            float: left;
            height: 40px;
            list-style: none;
            margin-right: 2px;
            margin-bottom: 3px;
            padding: 0px;
            position: relative;
            width: auto;
        }

        .highcharts-stocktools-toolbar li ul {
            display: none;
            left: 0px;
            padding-left: 0px;
            position: absolute;
            z-index: 125;
        }

        .highcharts-stocktools-toolbar li:hover {
            background-color: #e6ebf5;
        }

        .highcharts-stocktools-toolbar li:hover ul {
            display: block;
        }

        .highcharts-stocktools-toolbar li ul li {
            margin-bottom: 0px;
            width: 160px;
        }

        .highcharts-stocktools-toolbar li > span.highcharts-menu-item-btn {
            background-repeat: no-repeat;
            background-position: 50% 50%;
            display: block;
            float: left;
            height: 100%;
            width: 40px;
        }

        .highcharts-stocktools-toolbar li > .highcharts-menu-item-title {
            color: #666;
            line-height: 40px;
            font-size: 0.876em;
            padding: 0px 10px 0px 5px;
        }

        .highcharts-indicators > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/indicators.svg');
        }

        .highcharts-label-annotation > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/label.svg');
        }

        .highcharts-circle-annotation > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/circle.svg');
        }

        .highcharts-rectangle-annotation > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/rectangle.svg');
        }

        .highcharts-segment > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/segment.svg');
        }

        .highcharts-arrow-segment > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/arrow-segment.svg');
        }

        .highcharts-ray > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/ray.svg');
        }

        .highcharts-arrow-ray > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/arrow-ray.svg');
        }

        .highcharts-infinity-line > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/line.svg');
        }

        .highcharts-arrow-infinity-line > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/arrow-line.svg');
        }

        .highcharts-horizontal-line > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/horizontal-line.svg');
        }

        .highcharts-vertical-line > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/vertical-line.svg');
        }

        .highcharts-elliott3 > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/elliott-3.svg');
        }

        .highcharts-elliott5 > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/elliott-5.svg');
        }

        .highcharts-crooked3 > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/crooked-3.svg');
        }

        .highcharts-crooked5 > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/crooked-5.svg');
        }

        .highcharts-measure-xy > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/measure-xy.svg');
        }

        .highcharts-measure-x > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/measure-x.svg');
        }

        .highcharts-measure-y > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/measure-y.svg');
        }

        .highcharts-fibonacci > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/fibonacci.svg');
        }

        .highcharts-pitchfork > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/pitchfork.svg');
        }

        .highcharts-parallel-channel > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/parallel-channel.svg');
        }

        .highcharts-toggle-annotations > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/annotations-visible.svg');
        }

        .highcharts-vertical-counter > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/vertical-counter.svg');
        }

        .highcharts-vertical-label > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/vertical-label.svg');
        }

        .highcharts-vertical-arrow > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/vertical-arrow.svg');
        }

        .highcharts-vertical-double-arrow > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/vertical-double-arrow.svg');
        }

        .highcharts-flag-circlepin > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/flag-elipse.svg');
        }

        .highcharts-flag-diamondpin > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/flag-diamond.svg');
        }

        .highcharts-flag-squarepin > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/flag-trapeze.svg');
        }

        .highcharts-flag-simplepin > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/flag-basic.svg');
        }

        .highcharts-zoom-xy > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/zoom-xy.svg');
        }

        .highcharts-zoom-x > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/zoom-x.svg');
        }

        .highcharts-zoom-y > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/zoom-y.svg');
        }

        .highcharts-full-screen > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/fullscreen.svg');
        }

        .highcharts-series-type-ohlc > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/series-ohlc.svg');
        }

        .highcharts-series-type-line > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/series-line.svg');
        }

        .highcharts-series-type-candlestick > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/series-candlestick.svg');
        }

        .highcharts-current-price-indicator > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/current-price-show.svg');
        }

        .highcharts-save-chart > .highcharts-menu-item-btn {
            background-image: url('https://code.highcharts.com/gfx/stock-icons/save-chart.svg');
        }

        li.highcharts-active {
            background-color: #e6ebf5;
        }

        /* Popup */

        .chart-wrapper {
            float: left;
            position: relative;
            width: 100%;
            background: white;
            padding-top: 10px;
        }

        /* Responsive design */

        @media screen and (max-width: 1024px) {
            .highcharts-stocktools-toolbar li > .highcharts-menu-item-title {
                display: none;
            }

            .highcharts-stocktools-toolbar li ul li {
                width: auto;
            }
        }

    </style>
</head>
<body>
<link rel="stylesheet" type="text/css" href="https://code.highcharts.com/css/annotations/popup.css">


<script src="{{ asset("/hicharts/code/highstock.js") }}"></script>
<script src="{{ asset("/hicharts/code/modules/data.js") }}"></script>

<script src="{{ asset("/hicharts//code/modules/drag-panes.js") }}"></script>

<script src="{{ asset("/hicharts/code/indicators/indicators.js") }}"></script>
<script src="{{ asset("/hicharts/code/indicators/bollinger-bands.js") }}"></script>
<script src="{{ asset("/hicharts/code/indicators/ema.js") }}"></script>

<script src="{{ asset("/hicharts/code/modules/annotations-advanced.js") }}"></script>

<script src="{{ asset("/hicharts/code/modules/full-screen.js") }}"></script>
<script src="{{ asset("/hicharts/code/modules/price-indicator.js") }}"></script>
<script src="{{ asset("/hicharts/code/modules/stock-tools.js") }}"></script>

<div class="chart-wrapper">
    <div class="highcharts-popup highcharts-popup-indicators">
        <span class="highcharts-close-popup">&times;</span>
        <div class="highcharts-popup-wrapper">
            <label for="indicator-list">Indicator</label>
            <select name="indicator-list">
                <option value="sma">SMA</option>
                <option value="ema">EMA</option>
                <option value="bb">Bollinger bands</option>
            </select>
            <label for="stroke">Period</label>
            <input type="text" name="period" value="14"/>
        </div>
        <button>Add</button>
    </div>
    <div class="highcharts-popup highcharts-popup-annotations">
        <span class="highcharts-close-popup">&times;</span>
        <div class="highcharts-popup-wrapper">
            <label for="stroke">Color</label>
            <input type="text" name="stroke"/>
            <label for="stroke-width">Width</label>
            <input type="text" name="stroke-width"/>
        </div>
        <button>Save</button>
    </div>
    <div class="highcharts-stocktools-wrapper highcharts-bindings-container highcharts-bindings-wrapper">
        <div class="highcharts-menu-wrapper">
            <ul class="highcharts-stocktools-toolbar stocktools-toolbar">
                <li class="highcharts-indicators" title="Indicators">
                    <span class="highcharts-menu-item-btn"></span>
                    <span class="highcharts-menu-item-title">Indicators</span>
                </li>
                <li class="highcharts-label-annotation" title="Simple shapes">
                    <span class="highcharts-menu-item-btn"></span>
                    <span class="highcharts-menu-item-title">Shapes</span>
                    <span class="highcharts-submenu-item-arrow highcharts-arrow-right"></span>
                    <ul class="highcharts-submenu-wrapper">
                        <li class="highcharts-label-annotation" title="Label">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Label</span>
                        </li>
                        <li class="highcharts-circle-annotation" title="Circle">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Circle</span>
                        </li>
                        <li class="highcharts-rectangle-annotation " title="Rectangle">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Rectangle</span>
                        </li>
                    </ul>
                </li>
                <li class="highcharts-segment" title="Lines">
                    <span class="highcharts-menu-item-btn"></span>
                    <span class="highcharts-menu-item-title">Lines</span>
                    <span class="highcharts-submenu-item-arrow highcharts-arrow-right"></span>
                    <ul class="highcharts-submenu-wrapper">
                        <li class="highcharts-segment" title="Segment">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Segment</span>
                        </li>
                        <li class="highcharts-arrow-segment" title="Arrow segment">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Arrow segment</span>
                        </li>
                        <li class="highcharts-ray" title="Ray">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Ray</span>
                        </li>
                        <li class="highcharts-arrow-ray" title="Arrow ray">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Arrow ray</span>
                        </li>
                        <li class="highcharts-infinity-line" title="Line">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Line</span>
                        </li>
                        <li class="highcharts-arrow-infinity-line" title="Arrow line">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Arrow</span>
                        </li>
                        <li class="highcharts-horizontal-line" title="Horizontal line">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Horizontal</span>
                        </li>
                        <li class="highcharts-vertical-line" title="Vertical line">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Vertical</span>
                        </li>
                    </ul>
                </li>
                <li class="highcharts-elliott3" title="Crooked lines">
                    <span class="highcharts-menu-item-btn"></span>
                    <span class="highcharts-menu-item-title">Crooked lines</span>
                    <span class="highcharts-submenu-item-arrow highcharts-arrow-right"></span>
                    <ul class="highcharts-submenu-wrapper">
                        <li class="highcharts-elliott3" title="Elliott 3 line">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Elliot 3</span>
                        </li>
                        <li class="highcharts-elliott5" title="Elliott 5 line">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Elliot 5</span>
                        </li>
                        <li class="highcharts-crooked3" title="Crooked 3 line">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Crooked 3</span>
                        </li>
                        <li class="highcharts-crooked5" title="Crooked 5 line">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Crooked 5</span>
                        </li>
                    </ul>
                </li>
                <li class="highcharts-measure-xy" title="Measure">
                    <span class="highcharts-menu-item-btn"></span>
                    <span class="highcharts-menu-item-title">Measure</span>
                    <span class="highcharts-submenu-item-arrow highcharts-arrow-right"></span>
                    <ul class="highcharts-submenu-wrapper">
                        <li class="highcharts-measure-xy" title="Measure XY">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Measure XY</span>
                        </li>
                        <li class="highcharts-measure-x" title="Measure X">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Measure X</span>
                        </li>
                        <li class="highcharts-measure-y" title="Measure Y">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Measure Y</span>
                        </li>
                    </ul>
                </li>
                <li class="highcharts-fibonacci" title="Advanced">
                    <span class="highcharts-menu-item-btn"></span>
                    <span class="highcharts-menu-item-title">Advanced</span>
                    <span class="highcharts-submenu-item-arrow highcharts-arrow-right"></span>
                    <ul class="highcharts-submenu-wrapper">
                        <li class="highcharts-fibonacci" title="Fibonacci">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Fibonacci</span>
                        </li>
                        <li class="highcharts-pitchfork" title="Pitchfork">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Pitchfork</span>
                        </li>
                        <li class="highcharts-parallel-channel" title="Parallel channel">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Parallel channel</span>
                        </li>
                    </ul>
                </li>
                <li class="highcharts-vertical-counter" title="Vertical labels">
                    <span class="highcharts-menu-item-btn"></span>
                    <span class="highcharts-menu-item-title">Counters</span>
                    <span class="highcharts-submenu-item-arrow highcharts-arrow-right"></span>
                    <ul class="highcharts-submenu-wrapper">
                        <li class="highcharts-vertical-counter" title="Vertical counter">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Counter</span>
                        </li>
                        <li class="highcharts-vertical-label" title="Vertical label">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Label</span>
                        </li>
                        <li class="highcharts-vertical-arrow" title="Vertical arrow">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Arrow</span>
                        </li>
                    </ul>
                </li>
                <li class="highcharts-flag-circlepin" title="Flags">
                    <span class="highcharts-menu-item-btn"></span>
                    <span class="highcharts-menu-item-title">Flags</span>
                    <span class="highcharts-submenu-item-arrow highcharts-arrow-right"></span>
                    <ul class="highcharts-submenu-wrapper">
                        <li class="highcharts-flag-circlepin" title="Flag circle">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Circle</span>
                        </li>
                        <li class="highcharts-flag-diamondpin" title="Flag diamond">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Diamond</span>
                        </li>
                        <li class="highcharts-flag-squarepin" title="Flag square">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Square</span>
                        </li>
                        <li class="highcharts-flag-simplepin" title="Flag simple">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Simple</span>
                        </li>
                    </ul>
                </li>
                <li class="highcharts-series-type-ohlc" title="Type change">
                    <span class="highcharts-menu-item-btn"></span>
                    <span class="highcharts-menu-item-title">Series type</span>
                    <span class="highcharts-submenu-item-arrow highcharts-arrow-right"></span>
                    <ul class="highcharts-submenu-wrapper">
                        <li class="highcharts-series-type-ohlc" title="OHLC">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">OHLC</span>
                        </li>
                        <li class="highcharts-series-type-line" title="Line">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Line</span>
                        </li>
                        <li class="highcharts-series-type-candlestick" title="Candlestick">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Candlestick</span>
                        </li>
                    </ul>
                </li>
                <li class="highcharts-save-chart right" title="Save chart">
                    <span class="highcharts-menu-item-btn"></span>
                </li>
                <li class="highcharts-full-screen right" title="Fullscreen">
                    <span class="highcharts-menu-item-btn"></span>
                </li>
                <li class="highcharts-zoom-x right" title="Zoom change">
                    <span class="highcharts-menu-item-btn"></span>
                    <span class="highcharts-submenu-item-arrow highcharts-arrow-right"></span>
                    <ul class="highcharts-submenu-wrapper">
                        <li class="highcharts-zoom-x" title="Zoom X">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Zoom X</span>
                        </li>
                        <li class="highcharts-zoom-y" title="Zoom Y">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Zoom Y</span>
                        </li>
                        <li class="highcharts-zoom-xy" title="Zooom XY">
                            <span class="highcharts-menu-item-btn"></span>
                            <span class="highcharts-menu-item-title">Zoom XY</span>
                        </li>
                    </ul>
                </li>
                <li class="highcharts-current-price-indicator right" title="Current Price Indicators">
                    <span class="highcharts-menu-item-btn"></span>
                </li>
                <li class="highcharts-toggle-annotations right" title="Toggle annotations">
                    <span class="highcharts-menu-item-btn"></span>
                </li>
            </ul>
        </div>
    </div>
    <div id="container" class="chart"></div>
</div>


<script type="text/javascript">
    function addPopupEvents(chart) {
        var closePopupButtons = document.getElementsByClassName('highcharts-close-popup');
        // Close popup button:
        Highcharts.addEvent(
            closePopupButtons[0],
            'click',
            function () {
                this.parentNode.style.display = 'none';
            }
        );

        Highcharts.addEvent(
            closePopupButtons[1],
            'click',
            function () {
                this.parentNode.style.display = 'none';
            }
        );

        // Add an indicator from popup
        Highcharts.addEvent(
            document.querySelectorAll('.highcharts-popup-indicators button')[0],
            'click',
            function () {
                var typeSelect = document.querySelectorAll(
                    '.highcharts-popup-indicators select'
                    )[0],
                    type = typeSelect.options[typeSelect.selectedIndex].value,
                    period = document.querySelectorAll(
                        '.highcharts-popup-indicators input'
                    )[0].value || 14;

                chart.addSeries({
                    linkedTo: 'aapl-ohlc',
                    type: type,
                    params: {
                        period: parseInt(period, 10)
                    }
                });

                chart.stockToolbar.indicatorsPopupContainer.style.display = 'none';
            }
        );

        // Update an annotaiton from popup
        Highcharts.addEvent(
            document.querySelectorAll('.highcharts-popup-annotations button')[0],
            'click',
            function () {
                var strokeWidth = parseInt(
                    document.querySelectorAll(
                        '.highcharts-popup-annotations input[name="stroke-width"]'
                    )[0].value,
                    10
                    ),
                    strokeColor = document.querySelectorAll(
                        '.highcharts-popup-annotations input[name="stroke"]'
                    )[0].value;

                // Stock/advanced annotations have common options under typeOptions
                if (chart.currentAnnotation.options.typeOptions) {
                    chart.currentAnnotation.update({
                        typeOptions: {
                            lineColor: strokeColor,
                            lineWidth: strokeWidth,
                            line: {
                                strokeWidth: strokeWidth,
                                stroke: strokeColor
                            },
                            background: {
                                strokeWidth: strokeWidth,
                                stroke: strokeColor
                            },
                            innerBackground: {
                                strokeWidth: strokeWidth,
                                stroke: strokeColor
                            },
                            outerBackground: {
                                strokeWidth: strokeWidth,
                                stroke: strokeColor
                            },
                            connector: {
                                strokeWidth: strokeWidth,
                                stroke: strokeColor
                            }
                        }
                    });
                } else {
                    // Basic annotations:
                    chart.currentAnnotation.update({
                        shapes: [{
                            'stroke-width': strokeWidth,
                            stroke: strokeColor
                        }],
                        labels: [{
                            borderWidth: strokeWidth,
                            borderColor: strokeColor
                        }]
                    });
                }
                chart.stockToolbar.annotationsPopupContainer.style.display = 'none';
            }
        );
    }

    Highcharts.setOptions({
        time: {
            timezoneOffset: -8*60
        }
    });

    {{--{{route("stock_data", ["date" => $date, "code" => $code])}}--}}
    //https://demo-live-data.highcharts.com/aapl-ohlcv.json
    Highcharts.getJSON('{{route("stock_data", ["date" => $date, "code" => $code])}}', function (data) {

        // split the data set into ohlc and volume
        var ohlc = [],
            stocks = [],
            stockY = data[0]['y'],
            dataLength = data.length,
            i = 0;
        var stocks_arr = [stockY];

        for (i; i < dataLength; i += 1) {
            stocks_arr.push(parseFloat(data[i]["value"]));
            stocks[data[i]["date"]] = data[i];
            ohlc.push([
                data[i]["date"], // the date
                parseFloat(data[i]["value"]), // open
            ]);

        }

        Highcharts.getJSON('{{route("general_data", ["date" => $date])}}', function (generalData) {

            var general_prices = [], general = [], i = 0;
            var generalY = generalData[0]['y'];

            var general_arr = [generalY];

            for (i; i < generalData.length; i += 1) {
                general[generalData[i]["date"]] = generalData[i];
                general_arr.push(parseFloat(generalData[i]["value"]));
                general_prices.push([
                    generalData[i]["date"], // the date
                    parseFloat(generalData[i]["value"]) // the volume
                ]);
            }


            var points = [], shapes = [];

            var chart = Highcharts.stockChart('container', {
                chart: {
                    events: {
                        load: function () {
                            addPopupEvents(this);
                        }
                    },

                },

                marker0: {
                    tagName: 'marker',
                    render: false, // if false it does not render the element to the dom
                    id: 'arrow_green',
                    children: [{
                        tagName: 'path',
                        d: 'M 10,0 C 0,0 0,10 10,10 C 12.5,7.5 12.5,7.5 20,5 C 12.5,2.5 12.5,2.5 10,0 Z'
                    }],
                    markerWidth: 20,
                    markerHeight: 20,
                    refX: 20,
                    refY: 5
                },
                marker1: {
                    children: [{
                        tagName: 'path',
                        d: 'M 10,0 C 0,0 0,10 10,10 C 12.5,7.5 12.5,7.5 20,5 C 12.5,2.5 12.5,2.5 10,0 Z'
                    }],
                    tagName: 'marker',
                    id: 'arrow_red',
                    markerWidth: 20,
                    markerHeight: 20,
                    refX: 10,
                    refY: 10
                },

                series: [{
                    type: 'line',
                    id: 'ohlc',
                    name: 'Price',
                    step: !0,
                    data: ohlc,
                    tooltip: {
                        pointFormatter: function() {
                            var date  = this.x;
                            return 'Stock: ' + this.y + "<br>" +
                                "High: " + stocks[date]['high'] + "<br>" +
                                "Low: " + stocks[date]['low'];
                        }
                    }

                }, {
                    type: 'line',
                    id: 'general',
                    name: 'General',
                    data: general_prices,
                    yAxis: 1,
                    tooltip: {
                        pointFormatter: function() {
                            var date  = this.x;
                            return 'General: ' + this.y + "<br>" +
                                "High: " + general[date]['high'] + "<br>" +
                                "Low: " + general[date]['low'];
                        }
                    }
                }],
                tooltip: {
                    crosshairs: [{
                        width: "1px",
                        hcolor: "#999999"
                    }],
                    xDateFormat: "%H:%M:%S",
                    shadow: !1,
                    useHTML: !0,
                },
                xAxis: {
                    labels: {
                        formatter: function() {
                            var localNow = new Date(this.value);
                            return localNow.getHours() + ":" + localNow.getMinutes()
                        }
                    }
                },
                yAxis: [{
                    labels: {
                        align: 'left'
                    },
                    height: '50%',
                    min: Math.min(...stocks_arr),
                    max: Math.max(...stocks_arr),
                    plotLines: [{
                        value: stockY,
                        color: "orange",
                        dashStyle: "Solid",
                        width: 2,
                        label: {
                            align: "right",
                            text: stockY
                        }
                    }],
                }, {
                    labels: {
                        align: 'left'
                    },
                    top: '50%',
                    height: '50%',
                    min: Math.min(...general_arr),
                    max: Math.max(...general_arr),
                    plotLines: [{
                        value: generalY,
                        color: "orange",
                        dashStyle: "Solid",
                        width: 2,
                        label: {
                            align: "right",
                            text: generalY
                        }
                    }],
                }],
                navigationBindings: {
                    events: {
                        selectButton: function (event) {
                            var newClassName = event.button.className + ' highcharts-active',
                                topButton = event.button.parentNode.parentNode;

                            if (topButton.classList.contains('right')) {
                                newClassName += ' right';
                            }

                            // If this is a button with sub buttons,
                            // change main icon to the current one:
                            if (!topButton.classList.contains('highcharts-menu-wrapper')) {
                                topButton.className = newClassName;
                            }

                            // Store info about active button:
                            this.chart.activeButton = event.button;
                        },
                        deselectButton: function (event) {
                            event.button.parentNode.parentNode.classList.remove('highcharts-active');

                            // Remove info about active button:
                            this.chart.activeButton = null;
                        },
                        showPopup: function (event) {

                            if (!this.indicatorsPopupContainer) {
                                this.indicatorsPopupContainer = document
                                    .getElementsByClassName('highcharts-popup-indicators')[0];
                            }

                            if (!this.annotationsPopupContainer) {
                                this.annotationsPopupContainer = document
                                    .getElementsByClassName('highcharts-popup-annotations')[0];
                            }

                            if (event.formType === 'indicators') {
                                this.indicatorsPopupContainer.style.display = 'block';
                            } else if (event.formType === 'annotation-toolbar') {
                                // If user is still adding an annotation, don't show popup:
                                if (!this.chart.activeButton) {
                                    this.chart.currentAnnotation = event.annotation;
                                    this.annotationsPopupContainer.style.display = 'block';
                                }
                            }

                        },
                        closePopup: function () {
                            this.indicatorsPopupContainer.style.display = 'none';
                            this.annotationsPopupContainer.style.display = 'none';
                        }
                    }
                },
                stockTools: {
                    gui: {
                        enabled: false
                    }
                },
                responsive: {
                    rules: [{
                        condition: {
                            maxWidth: 800
                        },
                        chartOptions: {
                            rangeSelector: {
                                inputEnabled: false
                            }
                        }
                    }]
                },
                plotOptions: {
                    line: {
                        animation: !1,
                        lineWidth: 1,
                        showInLegend: !1,
                        dataGrouping: {
                            enabled: !1
                        },
                        marker: {
                            states: {
                                hover: {
                                    enabled: !1
                                },
                            }
                        },
                        states: {
                            hover: {
                                lineWidth: 1
                            },
                            inactive: {
                                opacity: 1,
                            },
                        },
                        zIndex: 9
                    },
                    column: {
                        animation: !1,
                        pointWidth: 1,
                        dataGrouping: {
                            enabled: !1
                        },
                        zIndex: 4
                    },
                    spline: {
                        animation: !1,
                        lineWidth: 2,
                        marker: {
                            states: {
                                hover: {
                                    enabled: !1
                                }
                            }
                        },
                        zIndex: 0
                    }
                },
                navigator: {
                    enabled: !1
                },
                scrollbar: {
                    enabled: !1
                },
                rangeSelector: {
                    enabled: !1
                },
                credits: false
            });

            Highcharts.getJSON('{{route("stock_order", ["date" => $date ,"code" => $code])}}', function (orders) {
                points = chart.series[0].points.reduce(function (t, e) {
                    t[e.x] = e.id;
                    return t;
                }, {});

                for(i=0; i < orders.length; i+=1){
                    var shapes = {
                        shapes: {
                            type: 'path',
                            points: [points[orders[i]['start']], points[orders[i]['end']]],
                            //markerEnd: 'arrow',
                            dashstyle: 'shortDash',
                            stroke: orders[i]['buy'] >= orders[i]['sell'] ? 'red' : 'lime'
                        },
                        langKey: "S: " + orders[i]['sell'] + "/B: " + orders[i]['buy']
                    };

                    chart.addAnnotation(shapes);
                }




                console.log(chart)


            });


        });

    });

</script>
</body>
</html>
