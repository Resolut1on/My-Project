var Event = {
    eventId:1037,
    eventAid:'h5_event_MT220150617',
    eventName:'MT220150617',
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
            Event.getPacketDetail();
        });      
        
        $("#cdk-record").unbind("click").bind("click",function(){
            TGDialogS('tc1');
        });
        
        $("#duihuan").unbind("click").bind("click",function(){
            showDialog.hide();
            var scrollHeight = $(document).height();
            $("body").animate({
                scrollTop: scrollHeight
            });
        });
        
        $("#dlsqewm").unbind("click").bind("click",function(){
            location.href = "http://ac.gtimg.com/h5_hd/mt2201506/images/QQ.jpg";
        });
        
        $("#dlwxewm").unbind("click").bind("click",function(){
            location.href = "http://ac.gtimg.com/h5_hd/mt2201506/images/weixin.jpg";
        });
        
        $("#dologonout").unbind("click").bind("click",function(){
            pt_logout.logout();
            pt_logout.clearCookie();
            setTimeout(function(){location.reload();},500);
        });
        
    },
    eventInfo: function(isLoad){
        $.ajax({
            type: 'post',
            url: "http://m.ac.qq.com/event/mt2201506/mt-action.php",
            dataType:"json",
            data: {'action':'event_info'},
            success: function(data) {
//                console.log(data.packets);
                if (data.status == 1) {
                    if (data.isEnd == 0){
                        //活动进行中
                        $("#unlogin").hide();
                        $("#logined").show();
                        if (data.vip_packet != "") {
                            $("#cdk1").text(data.vip_packet.cdkey1);
                            $("#cdk2").text(data.vip_packet.cdkey2);
                            $("#cdk3").text(data.vip_packet.cdkey3);
                        }
                        $("#login_qq_span").text(data.nickname);

                        if (!isLoad){
                            Event.goLogin();
                        }
                    } else {
                        
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
    getPacketDetail: function(){
        $.ajax({
            type: 'post',
            url: "http://m.ac.qq.com/event/mt2201506/mt-action.php",
            dataType:"json",
            data: {'action':'get_packet'},
            success: function(data) {
                
                if (data.status == 1) {
                    
                    //礼包领取、兑换
                    if (data.msg) {
                        FreeDialog.alert(data.msg);
                    }
                    $("#cdk1").text(data.cdkey1);
                    $("#cdk2").text(data.cdkey2);
                    $("#cdk3").text(data.cdkey3);
                    TGDialogS('tc1');
                    
                } else if (data.status == -20) {
                    Event.vipPay(data.uin);
                } else if (data.status == -2) {
                    //礼包被抢光
                    FreeDialog.alert(data.msg);
                } else if (data.status == -3) {
                    FreeDialog.alert(data.msg);
                } else if (data.status == -99) {
                    Event.goLogin();
                } else if (data.status == -96) {
                    FreeDialog.alert(data.msg);
                } else if (data.status == -95) {
                    FreeDialog.alert(data.msg);
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

function TGDialogS(e){
	need("biz.dialog-min",function(Dialog){
		Dialog.show({
			id:e,
			bgcolor:'#000', //弹出“遮罩”的颜色，格式为"#FF6600"，可修改，默认为"#fff"
			opacity:50      //弹出“遮罩”的透明度，格式为｛10-100｝，可选
		});
	});
}
function closeDialog(){
	need("biz.dialog-min",function(Dialog){
		Dialog.hide();
	});
}