var EventCommon = {
    init:function (){
        EventCommon.setMsgWin();
        EventCommon.setCancelMsgWin();
        EventCommon.setCollectWin();
        EventCommon.bindEvent();
    },
    bindEvent: function() {        
        
        
    },
    setMsgWin: function(){
        var html = '<div class="dialog dn" id="event_msg_win">';
        html += '<div class="dialog-bb">';
        html += '<a href="javascript:EventCommon.closeDialog();" class="dialog-close" title="点击关闭">点击关闭</a>';
        html += '<div class="dialog-notie dialog-con">';
        html += '<h2 class="dialog-month-tit">枪神纪漫画站</h2>';
        html += '<p class="dialog-check-pone txtc pop_text_if"><span class="pop_ico_true"></span></p>';
        html += '<div class="dialog-awardsure-btn">';
        html += '<a href="javascript:EventCommon.closeDialog();" class="btn-sure" title="确定">确定</a>';
        html += '</div></div></div></div>';
        
        $(html).appendTo("body");
    },
    setCancelMsgWin: function(){
        var html = '<div class="dialog dn" id="event_msg_cancel_win">';
        html += '<div class="dialog-bb">';
        html += '<a href="javascript:EventCommon.closeDialog();" class="dialog-close" title="点击关闭">点击关闭</a>';
        html += '<div class="dialog-notie dialog-con">';
        html += '<h2 class="dialog-month-tit">枪神纪漫画站</h2>';
        html += '<p class="dialog-check-pone txtc pop_text_if"><span class="pop_ico_clare"></span></p>';
        html += '<p class="notice txtc"></p>';
        html += '<div class="dialog-awardsure-btn">';
        html += '<a href="javascript:EventCommon.closeDialog();" class="btn-sure" title="确定">确定</a></div></div></div></div>';

        $(html).appendTo("body");
    },
    setCollectWin: function() {
        var html = '<div id="event_collect_win" class="dialog dn"><div class="dialog-bb">';
        html += '<a title="点击关闭" class="dialog-close" href="javascript:EventCommon.closeDialog();">点击关闭</a>';
        html += '<div class="dialog-check dialog-con">';
        html += '<h2 class="dialog-month-tit">枪神记漫画站</h2>';
        html += '<div class="dialog-check-select"><p id="dialog-check-bd" class="dialog-check-pone">收藏成功！</p></div>';
        html += '<div class="dialog-check-btn"><a title="确定" class="btn-sure m-auto" href="javascript:EventCommon.closeDialog();">确定</a></div></div></div></div>';
        
        $(html).appendTo("body");
    },
    popLotteryWin: function(index, msg){
        switch (index)
        {
            case 1:
            case 10:
            case 11:
            case 12:
            case 13:
            case 14:
            case 15:
            case 16:
            case 21:
                EventCommon.TGDialogS("event_msg_win");
                $("#event_msg_win .dialog-check-pone").html('<span class="pop_ico_clare"></span>'+msg);
                break;
            case 20:
                EventCommon.TGDialogS("event_msg_cancel_win");
                $("#event_msg_cancel_win .dialog-check-pone").html('<span class="pop_ico_clare"></span>'+msg);
                break;        
            case -95:
                EventCommon.TGDialogS("event_msg_cancel_win");
                $("#event_msg_cancel_win .dialog-check-pone").html('<span class="pop_ico_clare"></span>'+msg);
                break;
            case -96:
            case 0:
                EventCommon.TGDialogS("event_msg_cancel_win");
                $("#event_msg_cancel_win .dialog-check-pone").html('<span class="pop_ico_clare"></span>'+msg);
                break;
            case -70:
                EventCommon.TGDialogS("event_msg_cancel_win");
                $("#event_msg_cancel_win .dialog-check-pone").html('<span class="pop_ico_clare"></span>'+msg);
                break; 
            case -80:
                $("#bind_game_area").click();
                break;
            case -99:
                AC.Page.showLogin(location.pathname);
            default:
                break;
        }
    },
    popLoginWin: function(index, msg, isLoad){
        switch (index)
        {
            case -95:
            case -96:
            case -97:
            case -98:
                alert(msg);
                break;
            case -99:
                if (!isLoad) {
                    AC.Page.showLogin(location.pathname);
                }
            default:
                break;
        }
    },
    popPayCompleteWin: function(index, msg){
        switch (index)
        {
            case -81:
                CF.viewLottery();
                break;      
            case 0:
            case -82:
            case -95:
                EventCommon.TGDialogS("event_msg_cancel_win");
                $("#event_msg_cancel_win .dialog-check-pone").html('<span class="pop_ico_clare"></span>'+msg);
                break;
            case 20:
                EventCommon.TGDialogS("event_pay_win");
                $("#event_pay_win span").html(msg);
                break;
            case -80:
                $("#bind_game_area").click();
                break;
            case -99:
                AC.Page.showLogin(location.pathname);
            default:
                break;
        }
    },
    popRoleWin: function(index, msg){
        switch (index)
        {
            case 0:
            case -1:
            case -2:
            case -3:
            case -4:
                alert(msg);
                break;
            default:
                break;
        }
    },
    TGDialogS:function(e){
        need("biz.dialog-min",function(Dialog){
            Dialog.show({
                id:e,
                bgcolor:'#000', //弹出“遮罩”的颜色，格式为"#FF6600"，可修改，默认为"#fff"
                opacity:50      //弹出“遮罩”的透明度，格式为｛10-100｝，可选
            });
        });
    },
    closeDialog: function(){
        need("biz.dialog-min",function(Dialog){
            Dialog.hide();
        });
    },
    playVideo:function(divId, elementId, vId, vWidth, vHeight, isAutoPlay){
        var video = new tvp.VideoInfo();
        video.setVid(vId);//视频vid
        var player = new tvp.Player(vWidth, vHeight);//视频宽高
        player.setCurVideo(video);
        player.addParam("autoplay", isAutoPlay);//是否自动播放，1为自动播放，0为不自动播放
        player.addParam("wmode","opaque");
        player.addParam("flashskin", "http://imgcache.qq.com/minivideo_v1/vd/res/skins/TencentPlayerMiniSkin.swf");//是否调用精简皮肤，不使用则删掉此行代码
        player.write(elementId);
        EventCommon.TGDialogS(divId);
    },
    closeVideo:function(e){
        EventCommon.closeDialog();
        document.getElementById(e).innerHTML='';
    },
    scrollLotteryList: function(){
        var rollH=$("#lottery_list").height();
        var listH=$("#lottery_list .carouselContent").height();
        if(listH>rollH){
            var martop=0;
            $("#lottery_list .carouselContent").clone().attr("class","c3_zj_list").appendTo("#lottery_list");
            function rollText(){
                martop++;
            
                if(martop>listH){
                    martop=0;
                    $("#lottery_list .carouselContent").css("margin-top",-martop);
                }
                else{
                    $("#lottery_list .carouselContent").css("margin-top",-martop);
                }
            }
            var timeInt=setInterval(rollText,50);
            $("#lottery_list").hover(
                function(){
                    clearInterval(timeInt);
                },
                function(){
                    timeInt=setInterval(rollText,50);
                }
            );
        }
    }
};

$(document).ready(function(){
    EventCommon.init();
});

///////////////////////////////////////////////////////////////////////////////////
// MultiSelect
// 推荐使用MultiSelect.create()来生成对象，参数不变
// handle_array   [handle_select1, handle_select2, ...]
// opt_data_array [opt_data1, opt_data2, ... ]
// opt_data       {t:text, v:value, s:selected, opt_data_array:[opt_data_array] }
// custom_onchange_fun_array [customer_onchange_fun1, customer_onchange_fun2, ...] 参数可选
/////////////////////////////////////////////////////////////////////////////////
var MultiSelect=function(select_array, opt_data_array, ext_opt_data_array, custom_onchange_fun_array)
{
    if ( select_array instanceof Array && select_array.length > 0 ) {

        this.select = select_array[0];
        this.left_selects = [];
        for (var i=1; i<select_array.length; ++i) {
            this.left_selects.push(select_array[i]);
        }

        this.opt_data_array = opt_data_array || [];
        this.ext_opt_data_array = ext_opt_data_array || [];
        
        if ( !custom_onchange_fun_array ) {
            custom_onchange_fun_array = [];
            for ( var i=0;i<select_array.length;++i ) {
                custom_onchange_fun_array.push(select_array[i].onchange || function(){} );
            }
        }

        this.custom_onchange_fun = custom_onchange_fun_array[0];
        this.left_custom_funs = [];
        for (var i=1; i<custom_onchange_fun_array.length; ++i) {
            this.left_custom_funs.push(custom_onchange_fun_array[i]);
        }

        this.init();
    }
}

MultiSelect.create=function(select_array, opt_data_array, ext_data_array, custom_onchange_fun_array)
{
    var obj = new MultiSelect(select_array, opt_data_array, ext_data_array, custom_onchange_fun_array);
    MultiSelect["_OBJ_"+MultiSelect._OBJECT_NUM++] = obj;
    return obj;
}

MultiSelect._OBJECT_NUM = 0;

MultiSelect.prototype.init=function()
{
    this._initOption();

    if ( this.left_selects.length>0 ) {
        this._initOnchangeHandler();
    }

    if ( this.select.onchange ) {
        this.select.onchange(0,1);
    }
    return;
}

MultiSelect.prototype.getSelectByIndex=function(index)
{
    if (index == 0) {
        return this;   
    }
    if (this.left_selects.length==0) {
        return null
    }
    return this.next.getSelectByIndex(index-1);
}

MultiSelect.prototype.getSelectByHandle=function(select_handle)
{
    if (select_handle==this.select) {
        return this;
    }
    if (this.left_selects.length==0) {
        return null;
    }
    return this.next.getSelectByHandle(select_handle);
}

MultiSelect.prototype._initOption=function()
{
    this.select.length = 0;
    this._createOption(this.ext_opt_data_array);
    this._createOption(this.opt_data_array);
}  

MultiSelect.prototype._createOptionDom=function(opt_data_array, opt_fragment)
{
    for ( var i=0; i<opt_data_array.length; ++i ) {
 
        var opt_data = opt_data_array[i];
        var o = document.createElement("option");

        if ( opt_data.t==undefined || opt_data.t==null ) {
            opt_data.t="";
        }
        
        if ( opt_data.v==undefined || opt_data.v==null ) {
            opt_data.v=opt_data.t;
        }
        o.setAttribute("value", opt_data.v);

        if ( opt_data.s ) {
            o.setAttribute("selected", true);
        }

        var t = document.createTextNode(opt_data.t);
        o.appendChild(t);
        opt_fragment.appendChild(o);
    }
}

MultiSelect.prototype._createOption=function(opt_data_array)
{
    for ( var i=0; i<opt_data_array.length; ++i ) {
 
        var opt_data = opt_data_array[i];

        if ( opt_data.t==undefined || opt_data.t==null ) {
            opt_data.t="";
        }
        
        if ( opt_data.v==undefined || opt_data.v==null ) {
            opt_data.v=opt_data.t;
        }

        this.select.options[this.select.length] = new Option(opt_data.t, opt_data.v, false, (opt_data.s==true ) );
    }
}

MultiSelect.CALL_TYPE = {};
MultiSelect.CALL_TYPE.INIT = 0;     // 初始化调用
MultiSelect.CALL_TYPE.PROGRAM = 1;  // 页面中显式调用select.onchange()
MultiSelect.CALL_TYPE.BROWSER = 2;  // 用户触发的onchange事件时调用

MultiSelect.prototype._initOnchangeHandler=function()
{
    var this_multi_select = this;
    var select_handle = this_multi_select.select;
    var custom_onchange_fun = this_multi_select.custom_onchange_fun;

    select_handle.onchange = function(event,init) {
        
        event = window.event || event;
        var call_type = MultiSelect.CALL_TYPE.INIT;

        if ( !init ) {
            if ( !event ) {
                call_type = MultiSelect.CALL_TYPE.PROGRAM;
            }
            else {
                call_type = MultiSelect.CALL_TYPE.BROWSER;
            }
        }

        var args = {
            event: event,
            select: select_handle,            
            call_type: call_type,
            multi_select: this_multi_select
        };

        if ( custom_onchange_fun(args)==false ) {
            return;
        }

        this_multi_select.next = new MultiSelect(this_multi_select.left_selects, 
                                                              this_multi_select._getNextSelectOptArray(select_handle.value),
                                                              this_multi_select._getNextExtSelectOptArray(select_handle.value),
                                                              this_multi_select.left_custom_funs);
    }
}

MultiSelect.prototype._getNextSelectOptArray=function(value)
{
    for ( var i=0; i<this.opt_data_array.length; ++i ) {
        if ( this.opt_data_array[i].v == value ) {
            return this.opt_data_array[i].opt_data_array;
        }
    }
    return [];
}

MultiSelect.prototype._getNextExtSelectOptArray=function(value)
{
    for ( var i=0; i<this.ext_opt_data_array.length; ++i ) {
        if ( this.ext_opt_data_array[i].v == value ) {
            return this.ext_opt_data_array[i].opt_data_array;
        }
    }
    
    if ( this.ext_opt_data_array.length <= 0 ) {
        return [];
    }
    return this.ext_opt_data_array[0].opt_data_array || [];
}


/*  |xGv00|4667f8824809eac1d28f7ef7ab3e430d */