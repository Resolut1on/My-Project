var AreaSelect = {
    init: function(){
        AreaSelect.setGameArea();
        AreaSelect.bindEvent();
    },
    bindEvent:function(){
        $("#bind_game_area").click(function(){
            EventCommon.TGDialogS("dialog_bd");
        });
    },
    setGameArea: function(){
        $.ajax({
            dataType:'script',
            scriptCharset:'gb2312',
            url:'http://gameact.qq.com/comm-htdocs/js/game_area/tgame_server_select.js',
            success:function(){
                AreaSelect.setGameAreaHtml();
                var selectors = [$("#gameRegion_select")[0], $("#gameRegion_select_sub")[0]];
                TGAMEServerSelect.showzone(selectors, [{t: "请选择大区",v: "",opt_data_array: [{t: "请选择服务器",v: ""}]}], TGAMEServerSelect.STD_DATA);
                
                //切换大区重置角色
                $("#gameRegion_select").on("change", function(){
                    AreaSelect.resetRole();
                });
                
                //切换服务器(子区)绑定角色
                $("#gameRegion_select_sub").on("change", function(){
                    AreaSelect.getRoleByAreaId();
                });

                //确认角色
                $("#btn_role_ok").off("click").on("click",function(){
                    AreaSelect.confirmRole();
                });
            }
        });
    },
    getRoleByAreaId: function(){
        var areaId = $("#gameRegion_select_sub").find("option:selected").val();
        if (areaId.length > 0) {
            $.ajax({
                type: 'post',
                url: "http://ac.qq.com/event/nz201506/nz-action.php",
                dataType:"json",
                data: {'action':'getRoleByAreaId','areaId':areaId},
                success: function(data) {
                    if (data.status == 1) {
                        if (data.role.ret == 2){
                           $("#roleId").html('<option value="'+data.role.characName+'">'+data.role.characName+'</option>');
                        } else if (data.role.ret == 1){
                            AreaSelect.resetRole();
                            alert('很抱歉，在该服务器上未获得角色信息！请检查您是否注册该游戏角色后，再来尝试哦！');
                        }
                    } else if (data.status == -2) {
                        alert('大区不能为空');
                    } else if (data.status ==  -99) {
                        AC.Page.showLogin(location.pathname);
                    } else if (data.status == -95) {
                        alert(data.msg);
                    }
                }
            });
        } else {
            AreaSelect.resetRole();
        }
    },
    confirmRole:function(){
        var areaId = $("#gameRegion_select_sub").find("option:selected").val();
        var areaName = $("#gameRegion_select_sub").find("option:selected").text();
        var roleName = $("#roleId").find("option:selected").val();
        
        if (areaId && areaName && roleName) {
            $.ajax({
                type: 'post',
                url: "http://ac.qq.com/event/nz201506/nz-action.php",
                dataType:"json",
                data: {'action':'confirmRole','areaId':areaId,'areaName':areaName,'roleName':roleName,'tokenkey':$("#tokenkey").val()},
                success: function(data) {
                    if (data.status == 1) {
                        EventCommon.closeDialog();
                    } else {
                        EventCommon.popRoleWin(data.status, data.msg);
                    }
                }
            });
        }
    },
    setGameAreaHtml: function(){
		var html="";

		html+="<div class='mod-dialog dialog-check' id='select_area'>";
		html+="<h2 class='tit'>选择绑定的角色</h2>";
		html+="<a title='点击关闭' class='ico-close ui-dialog-close' href='javascript:showDialog.hide()'></a>";
		html+="<div class='check-con c'>";
		html+="<span class='check-tit l'>选择绑定的角色</span>"
		html+="<select name='' id='gameRegion_select'>";
		html+="<option value='请选择大区'>请选择大区</option>";
		html+="</select>";
		html+="<select name='' id='gameRegion_select_sub'>";
		html+="<option value='请选择服务器'>请选择服务器</option>";
		html+="</select>";
		html+="<select name='' id='roleId'>";
		html+="<option value='请选择角色'>请选择角色</option>";
		html+="</select>";
		html+="</div>";
		html+="<div class='btn-wrap'>";
		html+="<a title='确定' class='btn-sure l ui-dialog-close' href='javascript:;' id='btn_role_ok'>确定</a>";
		html+="<a title='取消' class='btn-cancel r ui-dialog-close' href='javascript:EventCommon.closeDialog();'>取消</a></div></div>";
	
		$(html).appendTo("body");
    },
    resetRole:function(){
        $("#roleId").html("<option value=''>选择角色</option>");
    }
};

$(document).ready(function(){
    AreaSelect.init();
});