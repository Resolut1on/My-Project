var SubQsj = {
    eventId:1017,
    eventName:'TPS20150315',
    comicId:529460,
    init: function(){
        // 道具切换
        SubQsj.awardRule();
        SubQsj.bindEvent();
        SubQsj.readHistory();
    },
    bindEvent: function(){
        //查看礼包
        $(".view_packet").unbind("click").bind("click",function(){
            SubQsj.viewPacket();
        });
        
        //新手礼包
        $("#get_packet").unbind("click").bind("click",function(){
            SubQsj.getPacket();
        });
        
        //添加或者取消收藏作品
        $("#btn_collect").bind("click",function(){
            SubQsj.collect();
        });
        
        //回答问题
        $("#reply").unbind("click").bind("click",function(){
            SubQsj.reply();
        });
        
        //统计
        $("[stats]").on("click",function(){
            var stats = $(this).attr('stats');
            if (stats.length > 0) {
                SubQsj.stats(stats);
            }

            var hottag = 'AC.EVENT.'+SubQsj.eventName+'.'+ stats;
            pgvSendClick({hottag: hottag});
        });
        
        //章节(1-20 21-40 41-60)列表
        $(".set-wrap").hover(
            function(){
                $(this).find(".set-content").show();
            },
            function(){
                $(this).find(".set-content").hide();
            }
        );
        
        //点击章节(1-20)切换图片
        $(".set-content a").live("click",function(){
            var _thisIndex = $(this).index();
            $(".works-number-list").eq(_thisIndex).show().siblings().hide();
        });

        //文本版的章节列表导航
        $("#works-set-sub a").live("click",function(){
            if ($(this).hasClass("set-no1")){
                $(".works-number-wrap").children().first().show().siblings().hide();
            }
            
            if ($(this).hasClass("set-no3")){
                $(".works-number-wrap").children().last().show().siblings().hide();
            }
        });

        $(".pureNum").die("click").live("click", function(){
            $("#monTicCounts").val("");
        });
        
         //页面输入框数字验证
        $("#monTicCounts").die("blur").live("blur",function(){
            if ($(this).val().length > 0) {
                $(this).val($(this).val().replace(/\D/g,''));
                $(this).val(Number($(this).val()));
            } else {
                $(this).val("");
            }
        });

        $("#monTicCounts").die("keypress").live("keypress",function(){
            if ($(this).val().length > 0 && $(this).val() != 0) {
                $(this).val($(this).val().replace(/\D/g,''));
            }
        });
        
        $("#monTicCounts").die("focus").live("focus",function(){
            $("#monTic_4").attr("checked",true);
        });

        $("#monTicCounts").die("keyup").live("keyup",function(){
            if ($(this).val().length > 0 && $(this).val() != 0) {
                $(this).val($(this).val().replace(/\D/g,''));
            }
        });
        
        //打赏支持窗口
        $("#btn-vip-pay").click(function() {
            EventCommon.TGDialogS("award_dialog");
        });

        //打赏支持窗口
        $("#btn-open-award").click(function() {
            EventCommon.TGDialogS("award_dialog");
        });

        //投月票
        $(".main-btn-month").click(function(month_dialog) {
            if (AC.Page.Core.hasLogin == "1") {
                $("#sendMonTicMsg").val("");
                $(".ft-month-red").html(AC.Page.Core.MT);
                $("a .userhead-b").attr("src",AC.Page.Core.avatar);
                EventCommon.TGDialogS("month_dialog");
            } else {
                AC.Page.showLogin();
            }
        });

        //确认投月票
        $("#send-btn").click(function(){
            var sendDiv = $("#month_dialog");
            var count = sendDiv.find("input[name='monTicCount']:checked").val();
            if (count == 'n'){
                count = $("#monTicCounts").val();
            }
            if (isNaN(count) || $.trim(count).length < 1 || parseInt(count) < 0) {
                EventCommon.TGDialogS("event_msg_cancel_win");
                $("#event_msg_cancel_win .dialog-check-pone").html("<span class='pop_ico_clare'></span>票数错误");
                return false;
            } else if (parseInt(count) > parseInt($('.ft-month-red').text())) {
                EventCommon.TGDialogS("event_msg_cancel_win");
                $("#event_msg_cancel_win .dialog-check-pone").html("<span class='pop_ico_clare'></span>票数不足");
                return false;
            }
            var monTicMsg = $('#sendMonTicMsg');
            var msg = monTicMsg.val().replace('\n', '');
            if ($.trim(msg).length < 1) {
                msg = monTicMsg.attr('placeholder');
            }
            
            var postData = {
                count: count,
                msg: msg,
                comicId: SubQsj.comicId
            };
            
            // 验证输入框长度
            var validata = {
                maxLen: function(value, maxLen){
                    return $.trim(value).length <= maxLen;
                }
            };
            var monTicMsgTip = $('#sendMonTicMsg_tip');
            var errClass = 'ac-tip-error';
            var borderClass = 'ac-tip-border';
            
            if(!validata.maxLen(postData.msg, 30)){
                monTicMsg.addClass(borderClass);
                monTicMsgTip.text("内容过长，应在30字以内");
                monTicMsgTip.addClass(errClass);
                return false;
            }

            SubQsj.loadData('/Comic/sendMonthTicket', postData, function(data) {
                if(data.status == 1) {
                    AC.Page.Core.MT = AC.Page.Core.MT - count;
                    $("#history-span").html("月票已累计投放："+data.history+"张");
                    $("#left-span").html("本月月票剩余："+data.left+"张");
                    EventCommon.TGDialogS("dialog-month-result");
                } else {
                    EventCommon.TGDialogS("event_msg_cancel_win");
                    $("#event_msg_cancel_win .dialog-check-pone").html("<span class='pop_ico_clare'></span>投月票失败");
                }
            });
         });

        //打赏窗口
        $(".main-btn-award").click(function() {
            if(AC.Page.Core.hasLogin == "1") {
                EventCommon.TGDialogS("award_dialog");
            } else {
                AC.Page.showLogin();
            }
        });

        //更多打赏
        $("#more-btn").click(function() {
            if(AC.Page.Core.hasLogin == "1") {
                EventCommon.TGDialogS("award_dialog");
            } else {
                AC.Page.showLogin();
            }
        });

        $(".btn-read").click(function() {
            var url = $(".mb-panel.current").find("a").attr("href");
            window.open(url);  
        });

        //确认打赏
        $("#pay-award-btn").click(function() {
            SubQsj.getPayCount();
            var postData = {
                unitPrice: SubQsj.unitprice,
                times:  SubQsj.times,
                msg: '支持作者，有我一份',
                comicId: SubQsj.comicId
            };
            
            SubQsj.loadData('/Comic/award', postData, function(data) {
                if (data.ret == 2) {
                    EventCommon.TGDialogS("award_dialog4");
                } else if (data.ret == -1) {
                    //转入Q币Q点
                    var uindex = $("#xaward-list-rule").find("li[class$='active']").index();
                    var tindex = SubQsj.times;
                    
                    $("#left_ticket").text("您剩余点券数："+data.dq);
                    $("#need_ticket").text("需支付点券：" + (SubQsj.unitprice * SubQsj.times));
                    
                    $("#xaward-list-noenough li:eq("+uindex+")").find(".ico-award-text").css("display","block").parent().siblings().find(".ico-award-text").hide();
                    $("#xaward-list-noenough li:eq("+uindex+")").find(".ico-award-text").html(tindex+"x");
                    EventCommon.TGDialogS("award_dialog2");
                } else {//-2
                    EventCommon.TGDialogS("event_msg_cancel_win");
                    $("#event_msg_cancel_win .dialog-check-pone").html("<span class='pop_ico_clare'></span>打赏失败");
                }
            });
        });

        //Q币Q点支付
        $("#pay-qb-btn").click(function() {
            var tokenKey = AC.Page.Core.token;
            var unitPrice = SubQsj.unitprice;
            var count = SubQsj.times;
            var msg = SubQsj.msg;
            SubQsj.payAwardWithQB(tokenKey,SubQsj.comicId,unitPrice,count,msg);
        });
    },
    payAwardWithQB:function(tokenKey,comicId,unitPrice,count,msg) {
        $.ajax({
            url: '/Comic/AwardPayWithQB',
            data: {tokenKey: tokenKey, comicId: comicId, unitPrice: unitPrice, count: count, msg: msg},
            type: 'POST',
            dataType: 'json',
            cached: false,
            success: function(data) {
                var ret = data.ret;
                var url_params = data.url_params;
                var context = data.context;
                result = 1;
                if(ret == 0)
                {
                    EventCommon.closeDialog();
                    fusion2.dialog.buy({
                        param : url_params,
                        sandbox: false,
                        context : context,
                        onSuccess : function (opt) { 
                            result = 2;
                        },
                        onCancel : function (opt) {
                            
                        },
                        onSend : function(opt) {
                            
                        }, 
                        onClose : function (opt) {
                            if(result == 2) {
                               EventCommon.TGDialogS("award_dialog4");
                            }
                        }
                    });
                } else {
                    EventCommon.TGDialogS("event_msg_cancel_win");
                    $("#event_msg_cancel_win .dialog-check-pone").html("<span class='pop_ico_clare'></span>系统繁忙!");
                }
            }
        });
    },
    loadData: function(url, params, callback) {
        params['tokenKey'] = AC.Page.Core.token;
        $.post(
            url,
            params,
            function(data) {
                if (data.status == -99) {
                    AC.Page.showLogin();
                } else {
                    if (undefined != callback) {
                        callback(data);
                    }
                }
        }, 'json');
    },
    awardRule: function(){
        $("#xaward-list-rule li").live("click",function(){
            var radioVal = $('#xaward-number-radio').find("input:checked").next().text();
            $(this).addClass("active").siblings().removeClass("active");
            $(this).find(".ico-award-text").text(radioVal);
            //打赏道具
            SubQsj.unitprice = $(this).attr("value");
        });
        $("#xaward-number-radio input").live("click",function(){
            var val = $(this).attr("id");
            $("#xaward-list-rule .active").find(".ico-award-text").text(val);
            //倍数
            SubQsj.times = val.replace('x','');
        });
    },
    getPayCount:function() {
        SubQsj.unitprice = $("#xaward-list-rule").find("li[class$='active']").val();
        SubQsj.times = $("#xaward-number-radio").find("input:checked").attr("id").replace('x','');
    },
    reply: function(){
        var answer = $('input:radio[name="answer"]:checked').val();
        if (answer == null) {
            EventCommon.TGDialogS("event_msg_cancel_win");
            $("#event_msg_cancel_win .dialog-check-pone").html("<span class='pop_ico_clare'></span>请选择答案");
            return false;
        }
        $.ajax({
            type: 'post',
            url: "http://ac.qq.com/event/tps/action.php",
            dataType:"json",
            data: {'action':'reply','answer':answer,'tokenkey':AC.Page.Core.token, 'type': 1},
            success: function(data) {
                if (data.status == 1 || data.status == 2) {
                    EventCommon.TGDialogS("event_msg_win");
                    $("#event_msg_win .dialog-check-pone").html("<span class='pop_ico_clare'></span>"+data.msg);
                    $("#take_packet").unbind("click").bind("click",function(){SubQsj.takePacket();}).removeClass("btn_get_dished").css("cursor","pointer");
                } else if (data.status == 0 || data.status == -95){
                    EventCommon.TGDialogS("event_msg_cancel_win");
                    $("#event_msg_cancel_win .dialog-check-pone").html("<span class='pop_ico_clare'></span>"+data.msg);
                } else if (data.status ==  -99) {
                    AC.Page.showLogin(location.pathname);
                }
            }
        });
    },
    collect: function() {
        var act = $("#btn_collect").attr("act");
        if (act == "add" || act == "del") {
            var action = act+"Collect";
            $.ajax({
                type: 'post',
                url: "http://ac.qq.com/event/tps/action.php",
                dataType:"json",
                data: {'action':action,'tokenkey':AC.Page.Core.token},
                success: function(data) {
                    if (data.status == 1){
                        EventCommon.TGDialogS("event_collect_win");
                        $("#btn_collect").removeClass("main-btn-sc").addClass("main-btn-sc-y").attr("act", "del");
                    } else if (data.status == 0) {
                        EventCommon.TGDialogS("event_msg_cancel_win");
                        $("#event_msg_cancel_win .dialog-check-pone").html("<span class='pop_ico_clare'></span>收藏发生错误");
                    } else if (data.status == 2) {
                        EventCommon.TGDialogS("event_msg_cancel_win");
                        $("#event_msg_cancel_win .dialog-check-pone").html("<span class='pop_ico_clare'></span>达到收藏夹上限");
                        $("#btn_collect").unbind("click").bind("click", function(){return false;}).css("cursor", "default").attr("act", "");
                    } else if (data.status == 3) {
                        EventCommon.TGDialogS("event_msg_cancel_win");
                        $("#event_msg_cancel_win .dialog-check-pone").html("<span class='pop_ico_clare'></span>已经收藏");
                        $("#btn_collect").removeClass("main-btn-sc").addClass("main-btn-sc-y").attr("act", "del");
                    } else if (data.status == 10){
                        EventCommon.TGDialogS("event_msg_win");
                        $("#event_msg_win .dialog-check-pone").html("<span class='pop_ico_clare'></span>取消收藏成功");
                        $("#btn_collect").removeClass("main-btn-sc-y").addClass("main-btn-sc").attr("act", "add");
                    } else if (data.status == -10){
                        EventCommon.TGDialogS("event_msg_cancel_win");
                        $("#event_msg_cancel_win .dialog-check-pone").html("<span class='pop_ico_clare'></span>取消收藏发生错误");
                        $("#btn_collect").removeClass("main-btn-sc").addClass("main-btn-sc-y").attr("act", "del");
                    } else if (data.status == -99) {
                        AC.Page.showLogin(location.pathname);
                    } 
                }
            });
        }
    },
    takePacket: function(){
        $("#take_packet").unbind("click").bind("click",function(){return false;});
        $.ajax({
            type: 'post',
            url: "http://ac.qq.com/event/tps/action.php",
            dataType:"json",
            data: {'action':'takePacket','tokenkey':AC.Page.Core.token, 'type': 1},
            success: function(data) {
                if (data.status == 1) {
                   EventCommon.popLotteryWin(data.status, data.msg);
                } else {
                    EventCommon.popLotteryWin(data.status, data.msg);
                }
                
                $("#take_packet").unbind("click").bind("click",function(){SubQsj.takePacket();});
            }
        });
    },
    getPacket: function() {
        $("#get_packet").unbind("click").bind("click",function(){return false;});
        $.ajax({
            type: 'post',
            url: "http://ac.qq.com/event/tps/action.php",
            dataType:"json",
            data: {'action':'getPacket','tokenkey':AC.Page.Core.token, 'type': 2},
            success: function(data) {
                if (data.status == 1) {
                   EventCommon.popLotteryWin(data.status, data.msg);
                } else {
                    EventCommon.popLotteryWin(data.status, data.msg);
                }
                
                $("#get_packet").unbind("click").bind("click",function(){SubQsj.getPacket();});
            }
        });
    },
    viewPacket: function(){
        $.ajax({
            type: 'post',
            url: "http://ac.qq.com/event/tps/action.php",
            dataType:"json",
            data: {'action':'viewPacket'},
            success: function(data) {
                if (data.status == 1) {
                    var html = '<tr><th>物品名称</th><th>查询明细</th><th>领取时间</th></tr>';
                    if (data.list.length > 0) {
                        for (i = 0; i < data.list.length; i++) {
                            html += '<tr><td>'+data.list[i]["name"]+'</td><td>'+data.list[i]["remark"]+'</td><td>'+data.list[i]["date"]+'</td></tr>';
                        }
                    } else {
                        html += '<tr><td colspan="3" style="text-align:center">暂无礼包记录</td></tr>';
                    }
                    EventCommon.TGDialogS("event_lottery_win");
                    $("#event_lottery_win table").html(html);
                } else if (data.status ==  -99) {
                    AC.Page.showLogin(location.pathname);
                }
            }
        });
    },
    stats : function(stats){
        //统计数据上报
        $.ajax({
            type: 'get',
            url: "http://ac.qq.com/event/event.php?action=stats",
            timeout : 1000,
            data: {event_id:SubQsj.eventId, stats:stats},
            success: function(data){}
        });	
    },
    statsVia : function () {
        if(document.referrer !== window.location.href) {
            var arr = document.referrer.split('/');
            if(arr[2] !== 1) {
                var reg = new RegExp("(^|&)_via=([^&]*)(&|$)"); 
                var r = window.location.search.substr(1).match(reg);  
                if (r!=null) {
                    SubQsj.stats(unescape(r[2]));
                }
            }
        }
    },
    readHistory: function(){
        var comicRecordList = eval(AC.Page.cookie('readRecord')) || [];
        var total = comicRecordList.length;
        if (total > 0) {
            for (var i = 0; i < total; i++) {
                if (SubQsj.comicId == comicRecordList[i][0]) {
                    comicChapterId = comicRecordList[i][2];
                    comicSeqNo = comicRecordList[i][4];
                    comicChapterTitle = comicRecordList[i][3];
                    var obj = $('#comic_history');
                    obj.html('['+comicSeqNo+'.'+comicChapterTitle+']');
                    obj.attr('href', '/ComicView/index/id/'+SubQsj.comicId+'/cid/'+comicChapterId);
                    obj.attr('target', "_blank");
                    break;
                }
            }
        }
    }
}

$(document).ready(function(){
    SubQsj.init();
});