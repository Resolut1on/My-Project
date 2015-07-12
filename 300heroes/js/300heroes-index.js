var Event = {
    eventId: 1043,
    eventAid: 'pc_event_300heros',
    eventName: '300heroes201507',
    init: function (isLoad) {
        AC.Page.LoadUserBaseInfo(1);
        Event.bindEvent();
        pgvMain();
        $("#tokenKey").val(AC.Page.Core.token);

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

        $(".mod-top-user-msg").live('click', function () {
            AC.Page.showLogin(location.pathname);
        });

        $("#getVipPacket").unbind("click").bind("click", function () {
            Event.getPacketDetail(2);
        });

        $("#getCommonPacket").unbind("click").bind("click", function () {
            Event.getPacketDetail(1);
        });

        $("#dologout").unbind("click").bind("click", function () {
            pt_logout.logout();
            pt_logout.clearCookie();
            setTimeout(function () {
                location.reload();
            }, 500);
        });

        $("#common_packet, #vip_packet").bind("click", function () {
            var scrollHeight = $(".box4 .container").offset().top;

            $("body, html").animate({
                scrollTop: scrollHeight
            });
        });


        $("#openVip").unbind("click").bind("click", function () {
            Event.openVip();
        });

    },
    eventInfo: function (isLoad) {
        var uin = AC.Page.Core.uin;
        var nickName = AC.Page.Core.nick;
        $.ajax({
            type: 'post',
            url: '300heroes-action.php',
            dataType: 'json',
            data: {'action': 'event_info', 'uin': uin, 'nickname': nickName},
            success: function (data) {
//                console.log(data);
                if (data.status == 1) {
                    if (data.isEnd == 0) {

                        if (!isLoad) {
                            AC.Page.showLogin(location.pathname);
                        }
                    } else {
                        //活动结束
                        Event.setPopHtml("活动已经结束！");
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
                url: "300heroes-action.php",
                dataType: "json",
                data: {'action': action, 'tokenkey': tokenkey , 'uin': uin},
                success: function (data) {
                    if (data.status == 1) {
                        //礼包领取、兑换
                        Event.setCdKey(data.cdkey);

                    } else {
                        EventCommon.popLotteryWin(data.status, data.msg, Event.eventAid, uin);
                    }
                }
            });
        }

    },
    openVip: function () {
        var tokenkey = AC.Page.Core.token;
        var uin = AC.Page.Core.uin;
        $.ajax({
            type: 'post',
            url: "300heroes-action.php",
            dataType: "json",
            data: {'action': 'open_vip', 'tokenkey': tokenkey},
            success: function (data) {
                if (data.status == 1) {
                    Event.miniPay(Event.eventAid, uin);
                } else {
                    EventCommon.popLotteryWin(data.status, data.msg, Event.eventAid, uin);
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
        EventCommon.TGDialogS("tc2");
    },
    setPopHtml: function (msg) {

        $("#font_msg").text(msg);
        EventCommon.TGDialogS("tc1");
    }
};

$(function () {

    Event.init(true);

    var $main = $('.main');

    var $skinList = $('.mod-skin li');
    $skinList.hover(function (e) {
        $(this).find('.over').animate({
            'top': '0px',
            left: '0px'
        })
    }, function (e) {
        $(this).find('.over').animate({
            'top': '220px',
            left: '0px'
        })
    });

});



