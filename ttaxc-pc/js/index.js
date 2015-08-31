var Event = {
    eventId: 1051,
    eventAid: 'pc_event_qjnn201508',
    eventName: 'qjnn201508',
    init: function (isLoad) {
        AC.Page.LoadUserBaseInfo(1);
        Event.bindEvent();

    },
    bindEvent: function () {
        //统计
        $("[stats]").on("click", function () {
            var stats = $(this).attr('stats');
            var hottag = 'AC.EVENT.' + Event.eventName + '.' + stats;
            pgvSendClick({hottag: hottag});
        });

        if (AC.Page.Core.hasLogin == 1) {
            $("#login_qq_span").text(AC.Page.Core.nick);
            Event.eventInfo(true);
            $("#logined").show();
            $("#unlogin").hide();
        }

        $("#dologin").unbind("click").bind("click", function () {
            AC.Page.showLogin(location.pathname);
        });

        $("#dologout").unbind("click").bind("click", function () {
            pt_logout.logout();
            pt_logout.clearCookie();
            setTimeout(function () {
                location.reload();
            }, 500);
        });

        $(".mod-top-user-msg").live('click', function () {
            AC.Page.showLogin(location.pathname);
        });

        $("#getVipPacket").unbind("click").bind("click", function () {
            Event.getPacketDetail(2);
        });

        $("#getCommonPacket").unbind("click").bind("click", function () {
            Event.getPacketDetail(1);
        });

        $("#viewGotPacket1, #viewGotPacket2").unbind("click").bind("click", function () {
            Event.viewPacket();
        });

//        $("#common_packet, #vip_packet").bind("click", function () {
//            var scrollHeight = $(".rp3 .contain").offset().top;
//
//            $("body, html").animate({
//                scrollTop: scrollHeight
//            });
//        });

        $("#open_vip").unbind("click").bind("click", function () {
            Event.openVip();
        });

//        $("#confirm_vip").unbind("click").bind("click", function () {
//            Event.openVip();
//        });

    },
    eventInfo: function (isLoad) {
        var uin = AC.Page.Core.uin;
        var nickName = AC.Page.Core.nick;
        $.ajax({
            type: 'post',
            url: 'http://ac.qq.com/event/qjnn201508/action.php',
            dataType: 'json',
            data: {'action': 'event_info', 'uin': uin, 'nickname': nickName},
            success: function (data) {

                if (data.status == 1) {
                    if (data.isEnd == 0) {

                        if (!isLoad) {
                            AC.Page.showLogin(location.pathname);
                        }
                    } else {
                        //活动结束
                        Event.setPopHtml("活动已经结束！");
                    }

                    if (data.common_packet) {
                        $("#getCommonPacket").hide();
                        $("#viewGotPacket1").show();
                    }

                    if (data.vip_packet) {
                        $("#getVipPacket").hide();
                        $("#viewGotPacket2").show();
                    }

                }
            }
        });
    },
    getPacketDetail: function (packetType) {

        var uin = AC.Page.Core.uin;
        var tokenkey = AC.Page.Core.token;
        if (packetType == 1 || packetType == 2) {
            var action = "";
            if (packetType == 1) {
                action = "getCommonPacket";
            } else if (packetType == 2) {
                action = "getVipPacket";
            }

            $.ajax({
                type: 'post',
                url: "http://ac.qq.com/event/qjnn201508/action.php",
                dataType: "json",
                data: {'action': action, 'tokenkey': tokenkey, 'uin': uin},
                success: function (data) {
                    if (data.status == 1) {
                        //礼包领取、兑换
                        Event.setCdKey(data.cdkey);
                        if (action == "getCommonPacket") {
                            $("#getCommonPacket").hide();
                            $("#viewGotPacket1").show();
                        } else if (action == "getVipPacket") {
                            $("#getVipPacket").hide();
                            $("#viewGotPacket2").show();
                        }

                    } else {
                        Event.popLotteryWin(data.status, data.msg, Event.eventAid, uin);
                    }
                }
            });
        }

    },
    viewPacket: function () {
        if (AC.Page.Core.hasLogin == "1") {
            $.ajax({
                type: 'post',
                url: "http://ac.qq.com/event/qjnn201508/action.php",
                dataType: "json",
                data: {'action': 'viewPacket'},
                success: function (data) {
//                    console.log(data.list);
//                    console.log(data.list[0].date);
                    if (data.status == 1) {
                        if (data.list[0]) {
                            $("#gotCommonPacket").html(data.list[0].cdkey);
                            $("#gotCommonTime").html(data.list[0].date);
                        }

                        if (data.list[1]) {
                            $("#gotVipPacket").html(data.list[1].cdkey);
                            $("#gotVipTime").html(data.list[1].date);
                        }


                        EventCommon.TGDialogS("tc4");
                    }
                }
            });
        } else {
            AC.Page.showLogin(location.pathname);
        }
    },
    openVip: function () {
        var tokenkey = AC.Page.Core.token;
        var uin = AC.Page.Core.uin;
        $.ajax({
            type: 'post',
            url: "http://ac.qq.com/event/qjnn201508/action.php",
            dataType: "json",
            data: {'action': 'open_vip', 'tokenkey': tokenkey},
            success: function (data) {
                if (data.status == 1) {
                    Event.miniPay(Event.eventAid, uin);
                } else {
                    Event.popLotteryWin(data.status, data.msg, Event.eventAid, uin);
                }
            }
        });
    },
    miniPay: function (eventAid, uin) {
        if (uin > 0 && eventAid.length > 0) {
            cashier.dialog.buy({
                type: 'service',
                scene: 'minipay',
                codes: 'MHVIP',
                aid: eventAid,
                channels: 'qdqb,kj,weixin',
                defaultChannel: 'qdqb',
                amount: '1',
                amountType: 'month',
                size: {w: 682, h: 450},
                target: '',
                context: '',
                onSuccess: function (opt) {
                    //Event.getPacketDetail(2);
                },
                onError: function (opt) {

                },
                onClose: function (opt) {
                    self.hideMask();
                },
                onNotify: function (opt) {
                },
                actid: ''
            });
        } else {
            AC.Page.showLogin(location.pathname);
        }
    },
    setCdKey: function (cdKey) {

        $("#font_cdkey").text(cdKey);
        EventCommon.TGDialogS("tc3");
    },
    setPopHtml: function (msg) {

        $("#font_msg").text(msg);
        EventCommon.TGDialogS("tc5");
    },
//    setPopVipHtml: function (msg) {
//
//        $("#font_openvip").text(msg);
//        EventCommon.TGDialogS("tc3");
//    },
//    setQrCodes: function (msg) {
//        EventCommon.TGDialogS("qr_codes");
//    },
    popLotteryWin: function (index, msg, eventAid, uin) {
        switch (index)
        {
            case -20:
                Event.miniPay(eventAid, uin);
                break;
            case -80:
                $("#bind_game_area").click();
                break;
            case -99:
                AC.Page.showLogin(location.pathname);
                break;
            default:
                Event.setPopHtml(msg);
                break;
        }
    }
};

$(function () {

    Event.init(true);

    $(".cont .lipin a").click(function () {
        var scroll_offset = $("#box2").offset();  //得到pos这个div层的offset，包含两个值，top和left
        $("body,html").animate({
            scrollTop: scroll_offset.top  //让body的scrollTop等于pos的top，就实现了滚动
        }, 500);
    });

});








