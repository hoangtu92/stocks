function Run() {
    var e, t, i, r, a, l, n, o, s, c, d, h, g, p = 270, u = [], f = [], y = [], b = [], w = 0,
        v = $("#container").width() >= 580;

    function x(e) {
        return new Date(e + 6e4 * new Date(e).getTimezoneOffset())
    }

    function P(e, t) {
        return e + 6e4 * t
    }

    function D(e) {
        return parseFloat(e.toFixed(2))
    }

    if (e) for (var m = 0, S = e.series.length; m < S; m++) e.series[0].remove();
    if (t = !1, id.indexOf("T") > -1 && (t = !0), $.ajax({
        url: "stock-chart-service.ashx",
        async: !0,
        cache: !1,
        data: {action: action, id: id, date: date, ck: ck},
        type: "get",
        dataType: "json",
        success: function (g) {
            if (null != g) {
                if (i = g.BasePrice, r = g.DataPrice, a = g.MaxPrice, l = g.MinPrice, n = g.NowDate, o = g.StartDate, s = g.RealInfo, c = g.ErrorCode, d = g.MaxPrice, h = g.MinPrice, (n - o) / 6e4 > p && (p = (n - o) / 6e4), l <= .01) {
                    var m = s.HighPrice - i;
                    s.HighPrice - i < i - s.LowPrice && (m = i - s.LowPrice), m = D(1.05 * (i + m));
                    var S = i - (m - i);
                    S < 0 && (S = 0), S = D(S), a = m, d = m, l = S, h = S
                }
                for (var L = 0, k = 0, Q = 0, T = r.length; k <= p; k++) {
                    var B = P(o, k);
                    if (B > n) {
                        L = k;
                        break
                    }
                    if (Q < T && r[Q][0] <= B) u.push([r[Q][0], r[Q][1]]), b.push([r[Q][0], t ? D(r[Q][2] / 1e8) : r[Q][2]]), f[r[Q][0]] = r[Q][3], y[r[Q][0]] = r[Q][4], w < r[Q][2] && (w = r[Q][2]), Q++; else {
                        if (0 == r.length) break;
                        0 == k ? u.push([B, i]) : u.push([B, u[u.length - 1][1]])
                    }
                }
                !function (e) {
                    var i = e / p * 100, r = parseInt(u.length / i * (100 - i), 10), a = P(o, e),
                        l = parseInt((P(o, p) - a) / r, 10);
                    if (t) for (var n = e; n < p; n++) u.push([P(o, n), null]); else for (n = 0; n < r; n++) u.push([a + (n + 1) * l, null])
                }(L), $("#container").css({
                    width: v ? "50%" : "100%",
                    height: v ? "293px" : "50%",
                    float: v ? "left" : "none"
                });
                U = s.HighPrice, V = s.LowPrice, N = i, Y = Math.abs(U - N), J = Math.abs(N - V), G = Y > J ? Y / N : J / N, j = Math.floor(G / .005);

                function M(e, t, i) {
                    var r = "<" + (i ? "div" : "span") + ' style="width:100%;height:100%;background-color:{0};color:{1};">' + e + "</" + (i ? "div" : "span") + ">";
                    return r = 0 == e ? "-" : e >= d ? (r = r.replace(/\{0}/g, "red")).replace(/\{1}/g, "white") : e <= h ? (r = r.replace(/\{0}/g, "green")).replace(/\{1}/g, "white") : e > t ? (r = r.replace(/\{0}/g, "")).replace(/\{1}/g, "red") : e < t ? (r = r.replace(/\{0}/g, "")).replace(/\{1}/g, "green") : (r = r.replace(/\{0}/g, "")).replace(/\{1}/g, "")
                }

                function A(e, t, i) {
                    var r = "";
                    return null == t && (t = ""), r = "<" + (i ? "div" : "span") + ' style="width:100%;height:100%;color:{0};">' + e + t + "</" + (i ? "div" : "span") + ">", r = e > 0 ? r.replace(/\{0}/g, "red") : e < 0 ? r.replace(/\{0}/g, "green") : r.replace(/\{0}/g, "")
                }

                function I(e) {
                    return 0 == e && (e = "-"), e
                }

                e = new Highcharts.StockChart({
                    chart: {renderTo: "container", height: 305, marginTop: 20},
                    series: [{type: "line", name: "成交價", data: u, color: "#4572A7", step: !0}, {
                        type: "column",
                        name: "成交量",
                        data: b,
                        yAxis: 1,
                        borderColor: "#AA4643"
                    }],
                    xAxis: {
                        gridLineWidth: 1,
                        gridLineDashStyle: "Dash",
                        gridLineColor: "#e0e0e0",
                        startOnTick: !1,
                        endOnTick: !1,
                        min: o - 1e4,
                        max: P(o, p),
                        tickPositions: [o, P(o, 60), P(o, 120), P(o, 180), P(o, 240)],
                        labels: {
                            formatter: function () {
                                return new Date(this.value + 6e4 * new Date(this.value).getTimezoneOffset()).getHours()
                            }
                        }
                    },
                    yAxis: [{
                        gridLineDashStyle: "Dash",
                        top: 20,
                        height: $.browser.msie ? 187 : 195,
                        offset: 40,
                        tickPositions: [l, D(i - .75 * (i - l)), D(i - .5 * (i - l)), D(i - .25 * (i - l)), i, D(i + .75 * (a - i)), D(i + .5 * (a - i)), D(i + .25 * (a - i)), a, a + 1e-4],
                        tickInterval: 0,
                        labels: {y: 2},
                        plotLines: [{value: i, color: "#D0D0D0", dashStyle: "Solid", width: 2}],
                        gridLineColor: "#e0e0e0",
                        showFirstLabel: !0
                    }, {
                        gridLineDashStyle: "Dash",
                        top: 238,
                        height: 35,
                        offset: 9,
                        minPadding: 0,
                        maxPadding: 0,
                        tickPositions: t ? [0, D(.5 * w / 1e8), D(w / 1e8), D(w / 1e8) + .001] : [0, parseInt(.5 * w, 10), w, w + .001],
                        tickInterval: 0,
                        labels: {
                            align: "right", y: 3, formatter: function () {
                                return function e(t) {
                                    var i = t.toString().split(".");
                                    return t.toString().length <= 3 || i.length > 1 ? t.toString() : e(t.toString().substr(0, t.toString().length - 3)) + "," + t.toString().substr(t.toString().length - 3)
                                }(parseInt(this.value, 10))
                            }, style: {color: "red"}
                        },
                        gridLineWidth: 1,
                        gridLineColor: "#e0e0e0"
                    }],
                    tooltip: {
                        crosshairs: [{width: "1px", hcolor: "#999999"}],
                        backgroundColor: "rgb(56, 114, 181)",
                        xDateFormat: "%H:%M",
                        shadow: !1,
                        useHTML: !0,
                        formatter: function () {
                            var e = x(this.x);
                            $(".highcharts-tooltip").show();
                            var t = '<span style="color:white">' + e.format("hh:mm") + "</span>";
                            return $.each(this.points, function (e, i) {
                                t += '<br/><span style="color:white">' + i.series.name + "  " + i.y + " </span>"
                            }), f[this.x] && y[this.x] ? (t += '<br/><span style="color:white">最高價  ' + f[this.x] + " </span>", t += '<br/><span style="color:white">最低價  ' + y[this.x] + " </span>") : ($(".highcharts-tooltip").hide(), !1)
                        }
                    },
                    plotOptions: {
                        line: {
                            animation: !1,
                            lineWidth: 1,
                            showInLegend: !1,
                            dataGrouping: {enabled: !1},
                            marker: {states: {hover: {enabled: !1}}},
                            states: {hover: {lineWidth: 1}},
                            zIndex: 9
                        },
                        column: {animation: !1, pointWidth: 1, dataGrouping: {enabled: !1}, zIndex: 4},
                        spline: {animation: !1, lineWidth: 2, marker: {states: {hover: {enabled: !1}}}, zIndex: 0}
                    },
                    navigator: {enabled: !1},
                    scrollbar: {enabled: !1},
                    rangeSelector: {enabled: !1},
                    credits: {enabled: !1}
                }), window.isChartReady = function () {
                    return !0
                }, $(".highcharts-container").hover(function () {
                    "none" == $(".highcharts-tooltip").css("display") && $(".highcharts-tooltip").show()
                }, function () {
                    $(".highcharts-tooltip").hide()
                });
                var O = s.BuyDataList[0], H = s.SellDataList[0];
                0 == O.Price && O.Qty > 0 && (s.BuyDataList[0].Price = "市價", s.BuyData = "市價"), 0 == H.Price && H.Qty > 0 && (s.SellDataList[0].Price = "市價", s.SellData = "市價"), $("#real-info").remove();
                var E = null, R = null, z = "";
                if (z += v ? '<div id="real-info" style="font-family:Lucida Grande,Lucida Sans Unicode,Verdana,Arial,Helvetica,sans-serif;width:48%;float:left;font-size:12px;border:">' : '<div id="real-info" style="font-family:Lucida Grande,Lucida Sans Unicode,Verdana,Arial,Helvetica,sans-serif;width:100%;float:left;font-size:12px;border:">', t) {
                    R = ["{DayDate}", "{SalePrice}", "{PriceDifference}", "{MagnitudeOfPrice}", "{HighPrice}", "{LowPrice}", "{AvgSalePrice}", "{Amount}", "{BuyTotalQty}", "{SellTotalQty}", "{PrvSalePrice}", "{PrvQty}"], z += '<table style="min-width:250px; border:0;margin:0 0 0 20px;border-collapse:collapse;height:293px;">';
                    for (k = 0, T = (E = ["日期", "成交", "漲跌", "漲幅", "最高", "最低", "均價", "金額(億)", "委買(萬張)", "委賣(萬張)", "昨收", "昨量(億)"]).length; k < T; k++) z += "<tr>", z += '<td style="text-align:left;width:50%;color:#4572A7;height:20px;">' + E[k] + "</td>", z += '<td style="text-align:right;width:50%;color:#333;height:20px;">' + R[k] + "</td>", z += "</tr>";
                    z += "</table>"
                } else {
                    R = ["{SalePrice}", "{MagnitudeOfPrice}", "{OpenPrice}", "{PriceDifference}", "{HighPrice}", "{BuyData}", "{LowPrice}", "{SellData}", "{AvgSalePrice}", "{Amount}", "{PrvSalePrice}", "{Qty}", "{PrvQty}", "{TotalQty}", "{InnerDisk}", "{OuterDisk}"], z += '<table style="width:100%;border:0;margin:0;border-collapse:collapse;height:147px;">';
                    for (k = 0, T = (E = ["成交", "漲幅", "開盤", "漲跌", "最高", "買價", "最低", "賣價", "均價", "金額(億)", "昨收", "單量", "昨量(張)", "總量", "內盤(張)", "外盤(張)"]).length; k < T; k += 2) z += "<tr>", z += '<td style="text-align:left;width:25%;color:#666;padding-left:3%;">' + E[k] + "</td>", z += '<td style="text-align:right;width:25%;color:#333;">' + R[k] + "</td>", z += '<td style="text-align:left;width:25%;color:#666;padding-left:3%;">' + E[k + 1] + "</td>", z += '<td style="text-align:right;width:25%;color:#333;">' + R[k + 1] + "</td>", z += "</tr>";
                    z += "</table>", z += '<table style="width:100%;border:0;margin:0;border-collapse:collapse;background-color:#999;height: 146px;">', z += "<tr>", z += '<td style="width:25%;background-color:#fff;color:#666;text-align:center;border:1px solid #999;">委買價&nbsp;/&nbsp;量　　</td>', z += '<td style="width:25%;background-color:#fff;color:#666;text-align:center;border:1px solid #999;">委賣價&nbsp;/&nbsp;量　　</td>', z += "</tr>", z += "<tr>";
                    for (k = 0; k < 2; k++) {
                        z += '<td style="width:25%;background-color:#fff;color:#666;text-align:center;border:1px solid #999;">', z += '<table cellpadding="1" cellspacing="0" width="100%">';
                        for (Q = 0; Q < 5; Q++) z += "<tr>", z += '<td width="47%" align="right">{' + (0 == k ? "Buy" : "Sell") + "DataList" + Q + "p}</td>", z += '<td width="6%">/</td>', z += '<td width="47%" align="right">{' + (0 == k ? "Buy" : "Sell") + "DataList" + Q + "q}</td>", z += "</tr>";
                        z += "</table>", z += "</td>"
                    }
                    z += "</tr>", z += "<tr>", z += '<td colspan="2" style="background-color:#fff;color:#666;text-align:center;border:1px solid #999;">', z += '<table cellpadding="1" cellspacing="0" width="100%">', z += "<tr>", z += '<td width="25%">委買張</td>', z += '<td width="25%" align="right">{BuyTotalQty}</td>', z += '<td width="25%">委賣張</td>', z += '<td width="25%" align="right">{SellTotalQty}</td>', z += "</tr>", z += "</table>", z += "</tr>", z += "</table>"
                }
                z += "</div>", v || (z = "<br />" + z), $("#container").after(z);
                var C = $("#real-info"), F = C.html(), W = x(n);
                F = (F = (F = (F = (F = (F = (F = (F = (F = (F = (F = (F = (F = (F = (F = (F = (F = F.replace(/\{SalePrice}/g, M(s.SalePrice, i, !t))).replace(/\{MagnitudeOfPrice}/g, A(s.MagnitudeOfPrice, "%", !t))).replace(/\{OpenPrice}/g, M(s.OpenPrice, i, !t))).replace(/\{PriceDifference}/g, A(s.PriceDifference, "", !t))).replace(/\{HighPrice}/g, M(s.HighPrice, i, !t))).replace(/\{BuyData}/g, M(s.BuyData, i, !t))).replace(/\{LowPrice}/g, M(s.LowPrice, i, !t))).replace(/\{SellData}/g, M(s.SellData, i, !t))).replace(/\{AvgSalePrice}/g, M(s.AvgSalePrice, s.PrvSalePrice, !t))).replace(/\{Amount}/g, (s.Amount / 1e8).toFixed(2))).replace(/\{PrvSalePrice}/g, s.PrvSalePrice)).replace(/\{DayDate}/g, W.getMonth() + 1 + "/" + W.getDate())).replace(/\{Qty}/g, s.Qty)).replace(/\{PrvQty}/g, t ? (s.PrvQty / 1e5).toFixed(2) : s.PrvQty)).replace(/\{TotalQty}/g, s.TotalQty)).replace(/\{InnerDisk}/g, s.InnerDisk)).replace(/\{OuterDisk}/g, s.OuterDisk);
                for (k = 0; k < 5; k++) F = (F = (F = (F = F.replace(new RegExp("{BuyDataList" + k + "p}", "g"), M(s.BuyDataList.length > 0 ? s.BuyDataList[k].Price : 0, i, !t))).replace(new RegExp("{BuyDataList" + k + "q}", "g"), I(s.BuyDataList.length > 0 ? s.BuyDataList[k].Qty : 0))).replace(new RegExp("{SellDataList" + k + "p}", "g"), M(s.SellDataList.length > 0 ? s.SellDataList[k].Price : 0, i, !t))).replace(new RegExp("{SellDataList" + k + "q}", "g"), I(s.SellDataList.length > 0 ? s.SellDataList[k].Qty : 0));
                F = t ? (F = F.replace(/\{BuyTotalQty}/g, Math.ceil(s.BuyTotalQty / 1e4))).replace(/\{SellTotalQty}/g, Math.ceil(s.SellTotalQty / 1e4)) : (F = F.replace(/\{BuyTotalQty}/g, s.BuyTotalQty)).replace(/\{SellTotalQty}/g, s.SellTotalQty), C.html(F).show(), $("#chg-btn:hidden").show(), e.tooltip.hide = function () {
                }, e.tooltip.hideCrosshairs = function () {
                }, 0 != c && (z = function (e) {
                    var t = '<div id="errorImage" style="width: 100%;height: 293px;position: absolute;top: 0px;left: 0px;background-image: url(images/{0});"></div>';
                    t = 124554 == e ? t.replace(/\{0}/g, "nostockinfo.png") : t.replace(/\{0}/g, "sysbusy.png");
                    return t
                }(c), $("body").append(z)), null != window.parent.document.getElementById("test") && (window.parent.document.getElementById("test").innerHTML = "後端資料：" + g.ReadTick + "ms")
            } else {
                var q = $("<img >");
                q.attr("src", "/notice/chart/images/nostockinfo.png"), $("#container").html(q)
            }
            var G, j, U, V, N, Y, J
        },
        error: function () {
            var e = $("<img >");
            e.attr("src", "/notice/chart/images/sysbusy.png"), $("#container").html(e)
        }
    }), t) {
        var L = '<div id="chg-btn" style="display:none;position:absolute;top:' + ($.browser.msie ? 3 : 1) + 'px;width:100%;">';
        L += '<div style="float:left;position:absolute;left:15px;top:277px;">', L += '<span style="font-size:12px;color:#666666;">(億)</span>', L += "</div>", L += "</div>", $("body").append(L)
    }
    g = "", v || (g += '<div id="chg-btn" style="top:0px;left:50px;position:absolute;">', g += '<input type="button" value="走勢圖" style="font-size:10px;background-color:#FED66D;border-width:1px;">', g += '<input type="button" value="報價" style="font-size:10px;background-color:#efefef;border-width:1px;">', g += "</div>", $("body").append(g)), $(":button").live("click", function () {
        $("#chg-btn :button").css("background-color", "#efefef"), "走勢圖" == $(this).val() ? ($("#real-info").hide(), $("#container").show()) : ($("#real-info").show(), $("#container").hide()), $(this).css("background-color", "#FED66D")
    })
}

Date.prototype.format = function (e) {
    var t = {
        "M+": this.getMonth() + 1,
        "d+": this.getDate(),
        "h+": this.getHours(),
        "m+": this.getMinutes(),
        "s+": this.getSeconds(),
        "q+": Math.floor((this.getMonth() + 3) / 3),
        S: this.getMilliseconds()
    };
    for (var i in /(y+)/.test(e) && (e = e.replace(RegExp.$1, (this.getFullYear() + "").substr(4 - RegExp.$1.length))), t) new RegExp("(" + i + ")").test(e) && (e = e.replace(RegExp.$1, 1 == RegExp.$1.length ? t[i] : ("00" + t[i]).substr(("" + t[i]).length)));
    return e
}, $(function () {
    Run()
});
