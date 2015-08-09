var Event = {
    eventId:1033,
    eventAid:'h5_event_pal201506',
    eventName:'PAL201506',
    init: function(isLoad){           
        Event.eventInfo(isLoad);
        Event.bindEvent();
        pgvMain();
    },
    bindEvent: function(){
        //统计
        $("[stats]").on("click",function(){
            var stats = $(this).attr('stats');
            var hottag = 'AC.EVENT.'+Event.eventName+'.'+ stats;
            pgvSendClick({hottag: hottag});
        });
        
        $("#dologin").unbind("click").bind("click",function(){
            Event.eventInfo(false);
        });
        
        $("#getVipPacket").unbind("click").bind("click",function(){
            Event.getPacketDetail(1);     
        });   
        
        $("#getComicPacket").unbind("click").bind("click",function(){
            Event.getPacketDetail(2);
        });    
    
        $("#dologonout").unbind("click").bind("click",function(){
            pt_logout.logout();
            pt_logout.clearCookie();
            setTimeout(function(){location.reload();},500);
        });
        
        $("#vip_exchange").unbind("click").bind("click",function(){
            location.href = "http://xj.qq.com/";
            hidePopOk('.pop1');
        });
        
        $("#common_exchange").unbind("click").bind("click",function(){
            location.href = "http://xj.qq.com/";
            hidePopOk('.pop2');
        });
        
        $("#openVip").unbind("click").bind("click",function(){
            Event.openVip();
        });
        
    
    },
    eventInfo: function(isLoad){
        $.ajax({
            type: 'post',
            url: 'pal-action.php',
            dataType:'json',
            data: {'action':'event_info'},
            success: function(data) {
                if (data.status == 1) {
                    if (data.isEnd == 0){
                        //活动进行中
                        $("#unlogin").hide();
                        $("#logined").show();
                        if (data.vip_packet != "") {
                            //已经领取VIP礼包
                        }
                        $("#login_qq_span").text(data.nickname);

                        if (!isLoad){
                            Event.goLogin();
                        }
                    } else {
                        //活动结束
//                        FreeDialog.alert("活动已经结束！");
                    }
                }
                else if (data.status == -99) {
                    if (!isLoad){
                        Event.goLogin();
                    }
                }
            }
        }); 
    },
    getPacketDetail: function(packetType){
        if (packetType == 1 || packetType == 2) {
            var action = "";
            if (packetType == 1) {
                action = "getVipPacket";
                $("#getVipPacket").unbind("click").bind("click",function(){return false;});
            } else if (packetType == 2) {
                action = "getComicPacket";
                $("#getComicPacket").unbind("click").bind("click",function(){return false;});
            }
            
            $.ajax({
                type: 'post',
                url: "pal-action.php",
                dataType:"json",
                data: {'action':action},
                success: function(data) {
                        if (data.status == 1) {
                            //礼包领取、兑换
                            if (packetType == 1) {
                                //动漫VIP礼包
                                $("#vip_cdk_box").text(data.cdkey);
                                pop('.pop1');
                            } else if (packetType == 2) {
                                //动漫专属福利
                                $("#common_cdk_box").text(data.cdkey);
                                pop('.pop2');
                            }
                            
                        } else if (data.status == 0) {
                            FreeDialog.alert(data.msg);
                        } else if (data.status == -20) {
                            Event.vipPay(data.uin);
                        } else if (data.status == -2) {
                            //礼包被抢光
                            if (packetType == 1) {
                                $("#vip_cdk_box").text(data.msg);
                                pop('.pop1');
                            } else if (packetType == 2) {
                                $("#common_cdk_box").text(data.msg);
                                pop('.pop2');
                            }

                        } else if (data.status == -3) {
                            if (packetType == 1) {
                                $("#vip_cdk_box").text(data.msg);
                                pop('.pop1');
                            } else if (packetType == 2) {
                                $("#common_cdk_box").text(data.msg);
                                pop('.pop2');
                            }
                            
                        } else if (data.status == -99) {
                            Event.goLogin();
                        } else if (data.status == -96) {
                            FreeDialog.alert(data.msg);
                        } else if (data.status == -95) {
                            FreeDialog.alert(data.msg);
                        }
                        
                        if (packetType == 1) {
                            $("#getVipPacket").unbind("click").bind("click",function(){Event.getPacketDetail(1);});
                        } else if (packetType == 2) {
                            $("#getComicPacket").unbind("click").bind("click",function(){Event.getPacketDetail(2);});
                        }          
                }

            });
        }
        
    },
    openVip: function() {
         $.ajax({
            type: 'post',
            url: "pal-action.php",
            dataType:"json",
            data: {'action':'open_vip'},
            success: function(data) {
                if (data.status == -99) {
                    Event.goLogin();
                } else if (data.status == -96) {
                    alert(data.msg);
                    FreeDialog.alert(data.msg);
                } else if (data.status == -95) {
                    alert(data.msg);
                    FreeDialog.alert(data.msg);
                } else if (data.status == 1) {
                    Event.vipPay(data.uin);
                } 
            }
        });
    },
    vipPay: function(uin) {
        if (uin != "") {
            var retUrl = encodeURIComponent(window.location.href);
            var url = "http://pay.qq.com/h5/index.shtml?m=buy&n=1&c=mhvip&pf=2208&u=" + uin + "&aid="+Event.eventAid+"&ru=" + retUrl ;
            window.location.href = url;
        }
    },
    goLogin: function(){
        var surl = window.location.href;
        window.location.href = 'http://ui.ptlogin2.qq.com/cgi-bin/login?style=9&appid=637009801&daid=43&s_url=' + encodeURIComponent(surl);
    }
};

$(document).ready(function(){
    Event.init(true);
});

function gE(e) {
    return document.querySelector(e)
}

function pop(e) {
    if (!gE('#pop-mask')) {
        gE(e).style.display = "block";
        var popH = gE(e).offsetHeight,
                popW = gE(e).offsetWidth;
        gE(e).style.cssText = "position:fixed;left:50%;display:block;top:50%;z-index:999;" + "margin-left:-" + popW / 2 + "px;" + "margin-top:-" + popH / 2 + "px;"
        var bgObj = document.createElement("div");
        bgObj.setAttribute('id', 'pop-mask');
        document.body.appendChild(bgObj);
        var conH = document.body.scrollHeight,
                viewH = document.documentElement.clientHeight;
        if (conH > viewH) {
            gE('#pop-mask').style.height = conH + "px";
        } else {
            gE('#pop-mask').style.height = viewH + "px";
        }
        hidePop(e);
    }
}

function hidePop(e) {
    gE('#pop-mask').addEventListener('click', function () {
        gE(e).style.display = "none";
        var bgObj = gE("#pop-mask");
        document.body.removeChild(bgObj);
    });
}

function hidePopOk(e) {
    gE(e).style.display = "none";
    var bgObj = gE("#pop-mask");
    document.body.removeChild(bgObj);
}

$(function () {
    $('.slide').slidesjs({
        width: 166,
        height: 307,
        navigation: {
            active: false, //是否显示左右箭头
            effect: "fade"//淡隐淡出
        },
        pagination: {
            active: true, //是否显示导航
            effect: 'slide'//左右滑动
        },
        effect: {
            fade: {
                speed: 400
            }
        },
        play: {
            interval: 2000, //自动播放时间间隔2S
            auto: true//是否自动播放
        }
    });
    $(".slidesjs-pagination-item").find("a").html('&nbsp&nbsp&nbsp&nbsp&nbsp');
});

