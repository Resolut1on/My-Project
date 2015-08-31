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
            url:'http://gameact.qq.com/comm-htdocs/js/game_area/jf_server_select.js',
            success:function(){
                AreaSelect.setGameAreaHtml();
                var selectors = [$("#gameRegion_select")[0]];
                JFServerSelect.showzone(selectors, [{t: "请选择大区",v: "",opt_data_array: [{t: "请选择角色",v: ""}]}], JFServerSelect.STD_DATA);
                
                //切换大区重置角色
                $("#gameRegion_select").on("change", function(){
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
        var areaId = $("#gameRegion_select").find("option:selected").val();
        if (areaId.length > 0) {
            $.ajax({
                type: 'post',
                url: "http://ac.qq.com/event/jfzr201508/action.php",
                dataType:"json",
                data: {'action':'getRoleByAreaId','areaId':areaId},
                success: function(data) {
                    if (data.status == 1) {
                        if (data.role.ret == 2){
                            $("#roleId").html('<option value="'+data.role.roleId+'">'+data.role.roleName+'</option>');
                        } else if (data.role.ret == 1){
                            AreaSelect.resetRole();
                            alert('很抱歉，在该服务器上未获得角色信息！请检查您是否注册该游戏角色后，再来尝试哦！');
                        }
                    } else if (data.status ==  -99) {
                        AC.Page.showLogin(location.pathname);
                    }
                }
            });
        } else {
            AreaSelect.resetRole();
        }
    },
    confirmRole:function(){
        var areaId = $("#gameRegion_select").find("option:selected").val();
        var areaName = $("#gameRegion_select").find("option:selected").text();
        var roleName = $("#roleId").find("option:selected").text();
        
        if (areaId && roleName !== "选择角色") {
            $.ajax({
                type: 'post',
                url: "http://ac.qq.com/event/jfzr201508/action.php",
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
        var html = '<div class="dialog dn" id="select_area">';
        html += '<div class="dialog-bb">';
        html += '<a href="javascript:EventCommon.closeDialog();" class="dialog-close" title="点击关闭">点击关闭</a>';
        html += '<div class="dialog-check dialog-con">';
        html += '<h2 class="dialog-month-tit">请选择大区</h2>';
        html += '<div class="dialog-check-select">';
        html += '<select id="gameRegion_select" ><option value="" selected="selected">选择大区</option></select>';
        html += '<select id="roleId"><option value="" selected="selected">选择角色 </option></select></div>';
        html += '<div class="dialog-check-btn c">';
        html += '<a href="javascript:void(0);" id="btn_role_ok" class="btn-sure l" title="确定">确定</a>';
        html += '<a href="javascript:EventCommon.closeDialog();" class="btn-sure r" title="取消">取消</a></div></div></div></div>';
        
        $(html).appendTo("body");
    },
    resetRole:function(){
        $("#roleId").html("<option value=''>选择角色</option>");
    }
};

$(document).ready(function(){
    AreaSelect.init();
});