var Event = {
    eventId: 1046,
    eventAid: 'pc_event_dwm201507',
    eventName: 'DNW201507',
    init: function (isLoad) {
        AC.Page.LoadUserBaseInfo(1);
        Event.bindEvent();
//        $("#tokenKey").val(AC.Page.Core.token);

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
            url: 'http://ac.qq.com/event/dnw201507/DNW-action.php',
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
                url: "http://ac.qq.com/event/dnw201507/DNW-action.php",
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

});

function copyToClipboard() {
    if (window.clipboardData) {
        window.clipboardData.clearData();
        window.clipboardData.setData("Text", document.getElementById('cdkey-ct').innerHTML);

    } else if (navigator.userAgent.indexOf("Opera") != -1) {
        window.location = txt;
    } else if (window.netscape) {
        try {
            netscape.security.PrivilegeManager.enablePrivilege("UniversalXPConnect");
        } catch (e) {
            alert("被浏览器拒绝！\n请在浏览器地址栏输入'about:config'并回车\n然后将'signed.applets.codebase_principal_support'设置为'true'");
        }
        var clip = Components.classes['@mozilla.org/widget/clipboard;1'].createInstance(Components.interfaces.nsIClipboard);
        if (!clip)
            return;
        var trans = Components.classes['@mozilla.org/widget/transferable;1'].createInstance(Components.interfaces.nsITransferable);
        if (!trans)
            return;
        trans.addDataFlavor('text/unicode');
        var str = new Object();
        var len = new Object();
        var str = Components.classes["@mozilla.org/supports-string;1"].createInstance(Components.interfaces.nsISupportsString);
        var copytext = txt;
        str.data = copytext;
        trans.setTransferData("text/unicode", str, copytext.length * 2);
        var clipid = Components.interfaces.nsIClipboard;
        if (!clip)
            return false;
        clip.setData(trans, null, clipid.kGlobalClipboard);
    }
    else {
        alert("对不起，您的浏览器不能使用复制功能，请手动复制！");
        return;
    }
    alert("CDKEY已经复制到剪贴板，赶紧去CDKEY兑换中心兑换礼包吧！");
}

$(function () {
    var cont = 0,
            len_in = 0,
            time = null;
    time = setInterval(function () {
        cont++;
        if (cont >= $('#ct' + (len_in + 1)).find('.slide-num').find('li').length) {
            cont = 0;
        }
        autoPlay(cont);
    }, 3000);
    $("#but_left").click(function () {
        cont--;
        if (cont <= -1) {
            cont = $('#ct' + (len_in + 1)).find('.slide-num').find('li').length;
        }
        autoPlay(cont);
    });
    $("#but_right").click(function () {
        cont++;
        if (cont >= $('#ct' + (len_in + 1)).find('.slide-num').find('li').length) {
            cont = 0;
        }
        autoPlay(cont);
    });
    $("#but_left1").click(function () {
        cont--;
        if (cont <= -1) {
            cont = $('#ct' + (len_in + 1)).find('.slide-num').find('li').length;
        }
        autoPlay(cont);
    });
    $("#but_right1").click(function () {
        cont++;
        if (cont >= $('#ct' + (len_in + 1)).find('.slide-num').find('li').length) {
            cont = 0;
        }
        autoPlay(cont);
    });
    $("#but_left2").click(function () {
        cont--;
        if (cont <= -1) {
            cont = $('#ct' + (len_in + 1)).find('.slide-num').find('li').length;
        }
        autoPlay(cont);
    });
    $("#but_right2").click(function () {
        cont++;
        if (cont >= $('#ct' + (len_in + 1)).find('.slide-num').find('li').length) {
            cont = 0;
        }
        autoPlay(cont);
    });

    $('#tab').find('li').each(function (index) {
        $(this).click(function () {
            $(this).addClass('on').siblings().removeClass('on');
            $('#ct' + (index + 1)).css('display', 'block').siblings().css('display', 'none');
            len_in = index;
        });
        $('#ct' + (index + 1)).find('.slide-num').find('li').each(function (nums) {
            $(this).mouseover(function () {
                cont = nums;
                clearInterval(time);
                $(this).addClass('on').siblings().removeClass('on');
                autoPlay(cont);
            });
            $(this).mouseout(function () {
                time = setInterval(function () {
                    cont++;
                    if (cont >= $('#ct' + (len_in + 1)).find('.slide-num').find('li').length) {
                        cont = 0;
                    }
                    autoPlay(cont);
                }, 4000);
            });
        });
    });
    var autoPlay = function (is) {
        $('.slide-pic').animate({
            'left': -is * $('.slide-pic').find('li').width() + 'px'
        }, 200);
        $('#ct' + (len_in + 1)).find('.slide-num').find('li').eq(is).addClass('on').siblings().removeClass('on');
    };
});


var nums = [], timer, n = $$("idSlider2").getElementsByTagName("li").length,
        st = new SlideTrans("idContainer2", "idSlider2", n, {
            onStart: function () {//设置按钮样式
                forEach(nums, function (o, i) {
                    o.className = st.Index == i ? "on" : "";
                })
            }
        });
for (var i = 1; i <= n; AddNum(i++)) {
}
;
function AddNum(i) {
    var num = $$("idNum").appendChild(document.createElement("li"));
    num.innerHTML = i--;
    num.onmouseover = function () {
        timer = setTimeout(function () {
            num.className = "on";
            st.Auto = false;
            st.Run(i);
        }, 200);
    }
    num.onmouseout = function () {
        clearTimeout(timer);
        num.className = "";
        st.Auto = true;
        st.Run();
    }
    nums[i] = num;
}
st.Run();


$$("idAuto").onclick = function () {
    if (st.Auto) {
        st.Auto = false;
        st.Stop();
        this.value = "自动";
    } else {
        st.Auto = true;
        st.Run();
        this.value = "停止";
    }
}
$$("idNext").onclick = function () {
    st.Next();
}
$$("idPre").onclick = function () {
    st.Previous();
}
$$("idTween").onchange = function () {
    switch (parseInt(this.value)) {
        case 2 :
            st.Tween = Tween.Bounce.easeOut;
            break;
        case 1 :
            st.Tween = Tween.Back.easeOut;
            break;
        default :
            st.Tween = Tween.Quart.easeOut;
    }
}




