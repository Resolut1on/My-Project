var AreaSelect = {
    init: function(){
        AreaSelect.setGameArea();
        AreaSelect.bindEvent();
    },
    bindEvent:function(){
        $("#bind_game_area").click(function(){
            EventCommon.TGDialogS("select_area");
        });
    },
    setGameArea: function(){
        $.ajax({
            dataType:'script',
            scriptCharset:'gb2312',
            url:'http://cf.qq.com/comm-htdocs/js/game_area/cf_server_select.js',
            success:function(){
                AreaSelect.setGameAreaHtml();
                var selectors = [$("#gameRegion_select")[0], $("#gameRegion_select_sub")[0]];
                CFServerSelect.showzone(selectors, [{t: "请选择大区",v: "",opt_data_array: [{t: "请选择服务器",v: ""}]}], CFServerSelect.STD_DATA);
                
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
                url: "http://ac.qq.com/event/cf201507/cf-action.php",
                dataType:"json",
                data: {'action':'getRoleByAreaId','areaId':areaId},
                success: function(data) {
                    if (data.status == 1) {
                        if (data.role.ret == 2){
                           $("#roleId").html('<option value="'+data.role.nickName+'">'+data.role.nickName+'</option>');
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
                url: "http://ac.qq.com/event/cf201507/cf-action.php",
                dataType:"json",
                data: {'action':'confirmRole','areaId':areaId,'areaName':areaName,'roleName':roleName,'tokenkey':AC.Page.Core.token},
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
        var html = '<div class="dialog_bd dialog" id="select_area" style="display:none;">';
        html += '<div class="dia_hd"><a href="javascript:showDialog.hide();" class="dia_close"></a></div>';
        html += '<div class="dia_c">';
        html += '<h3 class="dia_title">选择绑定角色</h3>';
        html += '<div name="bd" class="dia_chose">';
        html += '<p>选择绑定的角色:</p><select class="dia_slt" name="bdSever" id="gameRegion_select"><option value="">选择大区</option></select>';
        html += '<select class="dia_slt" name="bdZone" id="gameRegion_select_sub"><option value="">选择服务器</option></select>';
        html += '<select class="dia_slt" name="bdRole" id="roleId"><option value="">选择角色</option></select>';
        html += '</div>';
        html += '<a href="javascript:void(0);" class="dia_btn dia_btn1 dia_btn2" id="btn_role_ok" title="确定">确定</a>';
        html += '<a href="javascript:EventCommon.closeDialog();" class="dia_btn dia_btn1" title="取消">取消</a>';
        html += '</div>';
        html += '</div>';

        $(html).appendTo("body");
    },
    resetRole:function(){
        $("#roleId").html("<option value=''>选择角色</option>");
    }
};

$(document).ready(function(){
    AreaSelect.init();
});