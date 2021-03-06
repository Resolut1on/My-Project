var Event = {
    eventId: 1038,
    eventAid: 'pc_event_jlz20150623',
    eventName: 'jlz201506',
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
        
        $("#download_game").unbind("click").bind("click", function () {
            EventCommon.TGDialogS("tc3");
        });

    },
    eventInfo: function (isLoad) {
        var uin = AC.Page.Core.uin;
        var nickName = AC.Page.Core.nick;
        $.ajax({
            type: 'post',
            url: 'http://ac.qq.com/event/jlz/jlz-action.php',
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
                url: "http://ac.qq.com/event/jlz/jlz-action.php",
                dataType: "json",
                data: {'action': action, 'tokenkey': tokenkey},
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
    miniPay: function(eventAid, uin) {
        if(uin > 0 && eventAid.length > 0){
            cashier.dialog.buy({ 
                type:'service',
                scene : 'minipay',
                codes:'MHVIP',
                aid: eventAid,
                channels:'qdqb,kj,weixin',
                defaultChannel:'qdqb',
                amount:'1',
                amountType:'month',
                size : {w: 682, h: 450},
                target:'',
                context:'',
                onSuccess : function (opt) { 
                    //Event.getPacketDetail(2);
                },
                onError : function (opt) { 

                },
                onClose : function (opt) { 
                    self.hideMask();
                },
                onNotify : function (opt) { 
                },
                actid:''
            });
        } else {
            AC.Page.showLogin(location.pathname);
        }   
    },
	setCdKey: function(cdKey){
		
		$("#font_cdkey").text(cdKey);
		EventCommon.TGDialogS("tc2");
	},
	setPopHtml: function(msg){
		
		$("#font_msg").text(msg);
		EventCommon.TGDialogS("tc1");
	}
};

$(function () {
    
    Event.init(true);

    var video = new tvp.VideoInfo();
    video.setVid("a0159p7vz7c");//视频vid
    var player = new tvp.Player(563, 319);//视频高宽
    player.setCurVideo(video);
    player.addParam("autoplay", "1");//是否自动播放，1为自动播放，0为不自动播放
    player.addParam("wmode", "opaque");
    player.addParam("pic", "http://ossweb-img.qq.com/images/roco/act/a20120925movie/video_pic.jpg");//默认图片地址
    player.addParam("flashskin", "http://imgcache.qq.com/minivideo_v1/vd/res/skins/TencentPlayerMiniSkin.swf");//是否调用精简皮肤，不使用则删掉此行代码
    player.write("videoCon");

});



