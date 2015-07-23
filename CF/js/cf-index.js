var Event = {
    eventId: 1045,
    eventAid: 'pc_event_cf201507',
    eventName: 'cf201507',
    init: function () {
        Event.bindEvent();
    },
    bindEvent: function () {
        AC.Page.LoadUserBaseInfo(1);
        //统计
        $("[stats]").on("click", function () {
            var stats = $(this).attr('stats');
            var hottag = 'AC.EVENT.' + Event.eventName + '.' + stats;
            pgvSendClick({hottag: hottag});
        });

        //查看礼包
        $(".view_packet").click(function(){
            Event.viewPacket();
        });

        //登录
        $("#dologin").unbind("click").bind("click", function () {
            if (AC.Page.Core.hasLogin == undefined) {
                AC.Page.showLogin(location.pathname);
            }
        });

        //导航登录
        $(".mod-top-user-msg").live('click', function () {
            if (AC.Page.Core.hasLogin == undefined) {
                AC.Page.showLogin(location.pathname);
            }
        });

        //开通vip
        $("#openVip").unbind("click").bind("click",function(){
            Event.openVip();
        });

        $("#open_vip").unbind("click").bind("click",function(){
            Event.openVip();
        });

        if (AC.Page.Core.hasLogin == "1") {
            $("#unlogin").hide();
            $("#logined").show();
            $("#login_qq_span").text(AC.Page.Core.nick);
        } else {
            $("#unlogin").show();
            $("#logined").hide();
        }
    },
    viewPacket: function(){
        if (AC.Page.Core.hasLogin == "1") {
            $.ajax({
                type: 'post',
                url: "http://ac.qq.com/event/cf201507/cf-action.php",
                dataType:"json",
                data: {'action':'viewPacket'},
                success: function(data) {
                    if (data.status == 1) {
                        var html = '<tr><th>物品名称</th><th>备注</th><th>领取时间</th></tr>';
                        if (data.list.length > 0) {
                            for (i = 0; i < data.list.length; i++) {
                                html += '<tr><td>'+data.list[i]["name"]+'</td><td>'+data.list[i]["remark"]+'</td><td>'+data.list[i]["date"]+'</td></tr>';
                            }
                        } else {
                            html += ' <td colspan="3" class="col99">暂无记录</td></tr>';
                        }
                        EventCommon.TGDialogS("event_lottery_win");
                        $("#event_lottery_win table").html(html);
                    } else if (data.status ==  -99) {
                        AC.Page.showLogin(location.pathname);
                    }
                }
            });
        } else {
            AC.Page.showLogin(location.pathname);
        }
    },
    getPacket: function (packetType) {
        if (AC.Page.Core.hasLogin == undefined) {
            AC.Page.showLogin(location.pathname);
        } else {
            if (packetType == 1 || packetType == 2  || packetType == 3 || packetType == 4) {
                var action = "";
                if (packetType == 1) {
                    action = "getCommonPacket";
                } else if (packetType == 2) {
                    action = "getVipPacket";
                } else if (packetType == 3) {
                    action = 'getBackPacket';
                } else if (packetType == 4) {
                    action = 'getNewComerPacket';
                }

                $.ajax({
                    type: 'post',
                    url: "http://ac.qq.com/event/cf201507/cf-action.php",
                    dataType: "json",
                    data: {'action': action, 'tokenkey': AC.Page.Core.token},
                    success: function (data) {
                        EventCommon.popWin(data.status, data.msg, Event.eventAid, AC.Page.Core.uin);
                    }
                });
            }
        }
    },
    openVip: function() {
         if (AC.Page.Core.hasLogin == 1) {
             $.ajax({
                 type: 'post',
                 url: "http://ac.qq.com/event/cf201507/cf-action.php",
                 dataType: "json",
                 data: {'action': 'open_vip'},
                 success: function (data) {
                     if (data.status == 1) {
                         Event.miniPay(Event.eventAid, data.uin);
                     } else {
                         EventCommon.popWin(data.status, data.msg, Event.eventAid, AC.Page.Core.uin);
                     }
                 }
             });
         } else {
             AC.Page.showLogin(location.pathname);
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
                amount: '1',
                amountType: 'month',
                size: {w: 682, h: 450},
                target: '',
                context: '',
                onSuccess: function (opt) {

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
    }
};

$(function () {
    Event.init();
});