var Event = {
    eventId: 1048,
    eventAid: 'pc_event_nz201507',
    eventName: 'NZ201507',
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

        $("#common_packet, #vip_packet").bind("click", function () {
            var scrollHeight = $(".rp3 .contain").offset().top;

            $("body, html").animate({
                scrollTop: scrollHeight
            });
        });

        $("#confirm_vip").unbind("click").bind("click", function () {
            Event.openVip();
        });

    },
    eventInfo: function (isLoad) {
        var uin = AC.Page.Core.uin;
        var nickName = AC.Page.Core.nick;
        $.ajax({
            type: 'post',
            url: 'action.php',
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
                url: "action.php",
                dataType: "json",
                data: {'action': action, 'tokenkey': tokenkey, 'uin': uin},
                success: function (data) {
                    if (data.status == 1) {
                        //礼包领取、兑换
                        Event.setCdKey(data.cdkey);

                    } else {
                        Event.popLotteryWin(data.status, data.msg, Event.eventAid, uin);
                    }
                }
            });
        }

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
                amount: '3',
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
        EventCommon.TGDialogS("tc1");
    },
    setPopHtml: function (msg) {

        $("#font_msg").text(msg);
        EventCommon.TGDialogS("tc2");
    },
//    setPopVipHtml: function (msg) {
//
//        $("#font_openvip").text(msg);
//        EventCommon.TGDialogS("tc3");
//    },
    setQrCodes: function (msg) {
        EventCommon.TGDialogS("qr_codes");
    },
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

    var video = new tvp.VideoInfo();
    video.setVid("c0158d9nce3");//视频vid
    var player = new tvp.Player(622, 316);//视频高宽
    player.setCurVideo(video);
    player.addParam("autoplay", "1");//是否自动播放，1为自动播放，0为不自动播放
    player.addParam("wmode", "opaque");
    player.addParam("pic", "http://ossweb-img.qq.com/images/roco/act/a20120925movie/video_pic.jpg");//默认图片地址
    player.addParam("flashskin", "http://imgcache.qq.com/minivideo_v1/vd/res/skins/TencentPlayerMiniSkin.swf");//是否调用精简皮肤，不使用则删掉此行代码
    player.write("videoCon");

});


//检查是否已登录，已登录则获取QQ号显示已登录状态
LoginManager.checkLogin(function () {
    document.getElementById("login_qq_span").innerHTML = LoginManager.getUserUin();//获取QQ号
});








