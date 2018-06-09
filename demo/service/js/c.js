(function() {
    "use strict";

    var apiDomain = 'http://chat.com/';

    // 创建聊天室 api
    var createLink = apiDomain + 'api/createChatRoom';
    // 加入聊天室 api
    var joinLink = apiDomain + 'api/joinChatRoom';
    // 加载我的聊天室
    var myRoomsLink = apiDomain + 'api/getChatRoom';
    // 聊天记录 api
    var historyUrl = apiDomain + 'api/history';
    // websocket 链接
    var wsLink = 'ws://127.0.0.1:8282';

    var info = {};
    var ws = null;

    var context = {};

    context['info'] = G('#info');
    context['error']    = G('#error');
    context['chat'] = G('#chat');

    context['advoise'] = G('#advoise');
    context['userType'] = G('#user_type');
    context['userID']   = G('#user_id');
    context['nickname'] = G('#nickname');
    context['startBtn'] = G('#start_chat');
    context['sessionList'] = G('#session_list');
    context['roomList'] = G('#room_list');
    context['message'] = G('#message');
    context['loadRooms'] = G('#load_rooms');

    console.log(context);

    // 检查会话列表中是否已经存在某个会话
    var existsSession = function(roomID){
        var items = G('.item' , context['sessionList'].get());
        var i = 0;
        var cur = null;

        for (; i < items.length; ++i)
        {
            cur = G(items.get()[i]);

            if (cur.data('id') === roomID) {
                return true;
            }
        }

        return false;
    };

    // 检查房间列表中是否已经存在某个房间
    var existsRoom = function(roomID){
        var items = G('.item' , context['roomList'].get());
        var i = 0;
        var cur = null;

        for (; i < items.length; ++i)
        {
            cur = G(items.get()[i]);

            if (cur.data('id') === roomID) {
                return true;
            }
        }

        return false;
    };

    // 检查是否存在会话窗口
    var existsChat = function(roomID){
        var items = G('.msg-item' , context['message'].get());
        var i = 0;
        var cur = null;

        for (; i < items.length; ++i)
        {
            cur = G(items.get()[i]);

            if (cur.data('id') === roomID) {
                return true;
            }
        }

        return false;
    };

    // 检查给定房间是否已经存在指定用户
    var existsUser = function(roomID , userType , userID){
        var room = findChat(roomID);
        var users = G('.users' , room).first();
        var itemList = G('.item-list' , users.get()).first();
        var items = G('.item' , itemList.get());
        var i = 0;
        var cur = null;

        for (; i < items.length; ++i)
        {
            cur = G(items.get()[i]);

            if (cur.data('userType') == userType && cur.data('id') == userID) {
                return true;
            }
        }

        return false;
    };

    // 根据 room_id 找到给定的会话
    var findSession = function(roomID){
        var items = G('.item' , context['sessionList'].get());
        var i = 0;
        var cur = null;

        for (; i < items.length; ++i)
        {
            cur = G(items.get()[i]);

            if (cur.data('id') === roomID) {
                return cur.get();
            }
        }

        throw new Error('未找到对应会话');
    };

    // 根据 room_id 找到给定的房间
    var findRoom = function(roomID){
        var items = G('.item' , context['roomList'].get());
        var i = 0;
        var cur = null;

        for (; i < items.length; ++i)
        {
            cur = G(items.get()[i]);

            if (cur.data('id') === roomID) {
                return cur.get();
            }
        }

        throw new Error('未找到对应房间');
    };

    // 根据 room_id 找到给定的房间
    var findChat = function(roomID){
        var items = G('.msg-item' , context['message'].get());
        var i = 0;
        var cur = null;

        for (; i < items.length; ++i)
        {
            cur = G(items.get()[i]);

            if (cur.data('id') === roomID) {
                return cur.get();
            }
        }

        throw new Error('未找到对应窗口');
    };

    // 定义会话事件
    var defineSessioinEvent = function(session){
        session = G(session);

        var sessionItems = G('.item' , context['sessionList'].get());
        var msgItems = G('.msg-item' , context['message'].get());

        session.loginEvent('click' , function(event){
            var tar = G(event.currentTarget);
            var id = tar.data('id');

            tar.highlight('cur' , sessionItems.get());

            var msgItem = findChat(id);
                msgItem = G(msgItem);

                msgItem.highlight('hide' , msgItems.get() , true);
        } , true , false);
    };

    // 消息事件
    var defineMessageEvent = function(item){
        item = G(item);

        var user = getUser();

        // 加载更多
        var loadMore = G('.load-more' , item.get()).first();
        var textInput = G('.text-input' , item.get()).first();
        var sendBtn = G('.send-btn' , item.get()).first();

        var page = 0;

        // 分页事件
        loadMore.loginEvent('click' , function(){
            page++;

        } , true , false);

        // 消息发送
        sendBtn.loginEvent('click' , function(event){
            var tar = G(event.currentTarget);
            var id = tar.data('id');
            var type = tar.data('type');
            var val = textInput.get().value;

            if (val === '') {
                alert('消息不能为空');
                return ;
            }

            var json = {
                msg_type: type ,
                room_id: id ,
                user_type: user['userType'] ,
                user_id: user['userID'] ,
                content: val
            };

            json = G.jsonEncode(json);

            ws.send(json);
        } , true , false);
    };

    // 创建聊天窗口
    var addChat = function(roomID , type){
        var chatItems = G('.msg-item' , context['message'].get());

        if (existsChat(roomID)) {
            var curChat = G(findChat(roomID));

            curChat.highlight('hide' , chatItems.get() , true);
            return curChat.get();
        }

        var div = document.createElement('div');
            div = G(div);
            div.data('id' , roomID);
            div.data('type' , type);
            div.addClass('msg-item');

        var html = [];

        html.push('<div class="history">');
        html.push('<div class="component-title">');
        html.push('<div class="subject">聊天记录</div>');
        html.push('<div class="more"></div>');
        html.push('</div>');

        html.push('<div class="content">');
        html.push('<div class="function">');
        html.push('<button class="btn-8 load-more">加载更多</button>');
        html.push('</div>');

        html.push('<div class="msg-list">');

        html.push('</div>');

        html.push('<div class="input">');
        html.push('<textarea class="textarea text-input"></textarea>');
        html.push('<button class="btn-2 send-btn" data-id="' + roomID + '" data-type="' + type + '">发送消息</button>');
        html.push('</div>');
        html.push('</div>');
        html.push('</div>');

        html.push('<div class="users">');
        html.push('<div class="component-title">');
        html.push('<div class="subject">聊天室成员（<span class="online red">2</span>/<span class="total">3</span>）</div>');
        html.push('</div>');

        html.push('<div class="item-list">');
        html.push('</div>');
        html.push('</div>');

        div.get().innerHTML = html.join('');

        context['message'].get().appendChild(div.get());

        chatItems = G('.msg-item' , context['message'].get());

        div.highlight('hide' , chatItems.get() , true);

        defineMessageEvent(div.get());

        return div.get();
    };

    // 添加会话列表
    var addSession = function(roomID , type){
        var sessionItems = G('.item' , context['sessionList'].get());

        if (existsSession(roomID)) {
            var curSession = G(findSession(roomID));

            // 高亮显示
            curSession.highlight('cur' , sessionItems.get());

            return ;
        }

        var div = document.createElement('div');
            div = G(div);
            div.addClass('item');
            div.data('id' , roomID);
            div.data('type' , type);
            div.get().textContent = roomID;

        context['sessionList'].get().appendChild(div.get());

        sessionItems = G('.item' , context['sessionList'].get());

        div.highlight('cur' , sessionItems.get());

        defineSessioinEvent(div.get());
    };

    // 给定房间添加消息记录
    var addChatRecord = function(roomID , name , msg){
        var cur     = findChat(roomID);
        var msgList = G('.msg-list' , cur).first();
        var div = document.createElement('div');
            div.className = 'item';
            div.textContent = name + ':' + msg;

        msgList.get().appendChild(div);
    };

    // 添加聊天室成员
    var addRoomUser = function(roomID , user , online , count){
        var cur     = findChat(roomID);
        var users   = G('.users' , cur).first();
        var _online  = G('.online' , cur).first();
        var total   = G('.total' , cur).first();
        var itemList = G('.item-list' , users.get()).first();

        _online.get().textContent = online;
        total.get().textContent = count;

        if (existsUser(roomID , user['user_type'] , user['user_id'])) {
            // 更新用户状态
            var items = G('item' , itemList.get());
            var i = 0;
            var c = null;

            for (; i < items.length; ++i)
            {
                c = G(items.get()[i]);

                if (c.data('userType') == user['user_type'] && c.data('id') == user['user_id']) {
                    // 更新用户状态
                    if (user['status'] === 'on') {
                        c.get().innerHTML = '<span class="green">在线</span>&nbsp;<span class="weight">' + user['nickname'] + '</span>';
                    } else {
                        c.get().innerHTML = '<span class="red">离线</span>&nbsp;<span class="weight">' + user['nickname'] + '</span>';
                    }

                    break;
                }
            }
            return ;
        }

        var div = document.createElement('div');
            div = G(div);
            div.addClass('item');
            div.data('userType' , user['user_type']);
            div.data('id' , user['user_id']);

        var html = [];

        html.push('<span class="' + (user['status'] == 'on' ? 'green' : 'red') + '">' + (user['status'] == 'on' ? '在线' : '离线') + '</span>&nbsp;');
        html.push('<span class="weight">' + (G.isValidVal(user['details']) ? user['details']['nickname'] : '无昵称') + '</span>');

        div.get().innerHTML = html.join('');

        itemList.get().appendChild(div.get());
    };

    // 加载最近历史记录
    var loadHistory = function(roomID){
        var user = getUser();
        var json = {
            msg_type: 'syn' ,
            room_id: roomID ,
            user_type: user['userType'] ,
            user_id: user['userID']
        };
        // 发送消息
        json = G.jsonEncode(json);

        // 发送
        ws.send(json);
    };

    // 加载聊天室成员
    var loadRoomUser = function(roomID){
        var user = getUser();

        var json = {
            msg_type: 'user' ,
            room_id: roomID ,
            user_type: user['userType'] ,
            user_id: user['userID']
        };
        // 发送消息
        json = G.jsonEncode(json);

        // 发送
        ws.send(json);
    };

    // 定义聊天室相关事件
    var defineChatRoomEvent = function(){
        var items = G('.item' , context['roomList'].get());

        items.loginEvent('click' , function(event){
            var tar = G(event.currentTarget);

            var id = tar.data('id');
            var type = tar.data('type');

            // 添加会话
            addSession(id , type);
            // 添加聊天窗口
            addChat(id , type);
            // 加载历史记录
            loadHistory(id);
            // 加载聊天室成员
            loadRoomUser(id);
        } , true , false);
    };

    // 设置聊天室
    var initChatRoom = function(){
        var user = getUser();

        var formData = {
            user_type: user['userType'] ,
            user_id: user['userID']
        };

        var sendData = G.getFormData(formData);

        G.ajax({
            url: myRoomsLink ,
            method: 'post' ,
            sendData: sendData ,
            success: function(json){
                var data = G.jsonDecode(json);

                if (data['status'] === 'failed') {
                    context['error'].get().textContent = data['msg'];
                } else {
                    var data = data['msg'];
                    var html = [];
                    var cur = null;

                    for (var i = 0; i < data.length; ++i)
                    {
                        cur = data[i];
                        html.push('<div class="item" data-id="' + cur['id'] + '" data-type="' + cur['type'] + '">' + cur['name'] + '</div>');
                    }

                    context['roomList'].get().innerHTML = html.join('');

                    defineChatRoomEvent();
                }
            }
        });
    };

    // 平台咨询
    context['advoise'].loginEvent('click' , function(){
        var user = getUser();

        createChatRoom('advoise' , user , function(room){
            var users = [];
                users.push({
                    user_type: user['userType'] ,
                    user_id: user['userID']
                });

            // 自身加入到聊天室
            joinChatRoom(room['id'] , users , function(){
                // 初始化聊天室
                initChatRoom();
                addSession(room['id'] , room['type']);
                addChat(room['id'] , room['type']);

                // 自动分配客服
                var json = {
                    msg_type: 'auto_allocate' ,
                    room_type: 'advoise' ,
                    room_id: room['id']

                };

                json = G.jsonEncode(json);

                // 发送数据
                ws.send(json);

                // 加载聊天室成员
                loadRoomUser(room['id']);
            });
        });
    } , true , false);

    // 直接加载即可
    // initChatRoom();

    // 加载用户加入的房间
    context['loadRooms'].loginEvent('click' , initChatRoom , true , false);


    context['startBtn'].loginEvent('click' , function(event){
        context['info'].addClass('hide');
        context['chat'].removeClass('hide');

        var user = getUser();

        console.log(user);

        ws = new WebSocket(wsLink);

        ws.onopen = function(event){
            var loginData = {
                msg_type: 'login' ,
                user_type: user['userType'] ,
                user_id: user['userID'] ,
                nickname: user['nickname'] ,
                thumb: user['thumb']
            };

            loginData = G.jsonEncode(loginData);

            this.send(loginData);
        };

        ws.onmessage = function(event){
            var json = event.data;
            var data = G.jsonDecode(json);

            if (data['msg_type'] === 'history') {
                // 消息记录同步
                synMsg(data['content']);
            } else if (data['msg_type'] === 'user') {
                var users = data['content'];
                var i = 0;
                var cur = null;

                for (; i < users['user'].length; ++i)
                {
                    cur = users['user'][i];
                    addRoomUser(users['room_id'] , cur , users['online'] , users['count']);
                }
            } else if (data['msg_type'] === 'syn') {
                console.log('手动填充历史记录');
            } else if (data['msg_type'] === 'advoise') {
                advoise(data);
            } else if (data['msg_type'] === 'notification') {
                // 用户上下线通知
                notification(data);
            } else {
                // 其他消息类型,这边暂时不要理他
                context['error'].get().textContent = '暂时不支持的消息类型';
            }

        };

        ws.onclose = function(){
            context['error'].get().textContent = 'websocket 已经关闭';
        };

        // 初始化获取我的聊天室
        initChatRoom();
    });

    // 创建聊天室
    function createChatRoom(type , user , fn){
        var formData = {
            msg_type: type ,
            user_type: user['userType'] ,
            user_id: user['userID']
        };

        formData = G.getFormData(formData);

        // 创建聊天室
        G.ajax({
            url: createLink ,
            method: 'post' ,
            sendData: formData ,
            success: function(json){
                var data = G.jsonDecode(json);

                if (data['status'] === 'failed') {
                    context['error'].get().textContent = data['msg'];
                } else {

                    if (G.getValType(fn) === 'Function') {
                        fn(data['msg']);
                    }
                }
            }
        });
    }

    // 加入俩天使
    function joinChatRoom(roomID , users , fn){
        var formData = {
            room_id: roomID ,
            users: G.jsonEncode(users)
        };

        var sendData = G.getFormData(formData);

        G.ajax({
            url: joinLink ,
            method: 'post' ,
            sendData: sendData ,
            success: function(json){
                var data = G.jsonDecode(json);

                if (data['status'] === 'failed') {
                    context['error'].get().textContent = data['msg'];
                } else {
                    if (G.getValType(fn) === 'Function') {
                        fn();
                    }
                }
            }
        });
    }

    // 最近消息同步
    function synMsg(data){
        if (data.length === 0) {
            context['error'].get().textContent = '没有会话记录';
            return ;
        }

        var i = 0;
        var n = 0;
        var cur = null;
        var cCur = null;

        for (; i < data.length; ++i)
        {
            cur = data[i];

            // 添加会话
            addSession(cur['id'] , cur['type']);
            addChat(cur['id'] , cur['type']);

            for (n = 0; n < cur['history'].length; ++n)
            {
                cCur = cur['history'][n];

                // 添加聊天记录
                addChatRecord(cur['id'] , cCur['nickname'] , cCur['content']);
            }

            // 聊天室成员
            loadRoomUser(cur['id']);
        }
    }

    // 平台咨询
    function advoise(data){
        initChatRoom();
        addSession(data['room_id'] , 'advoise');
        addChat(data['room_id'] , 'advoise');
        addChatRecord(data['room_id'] , data['nickname'] , data['content']);
    }

    function getUser(){
        // 用户
        return {
            userType: context['userType'].get().value ,
            userID: context['userID'].get().value ,
            nickname: context['nickname'].get().value ,
            thumb: ''
        };
    }

    function notification(data){
        var sessionItems = G('.item' , context['sessionList'].get());
        var i = 0;
        var cur = null;
        var id = null;
        var type = null;
        var chat = null;
        var users = null;
        var itemList = null;
        var userItems = null;
        var n = 0;
        var cCur = null;
        var online = null;
        var count = null;

        for (; i < sessionItems.length; ++i)
        {
            cur = G(sessionItems.get()[i]);
            id = cur.data('id');
            type = cur.data('type');

            chat = findChat(id);
            users = G('.users' , chat).first();
            itemList = G('.item-list' , users.get()).first();
            userItems = G('.item' , itemList.get());
            online = G('.online' , users.get()).first();
            count = parseInt(online.get().textContent);

            if (data['status'] === 'on') {
                count++;
            } else {
                count--;
            }

            online.get().textContent = count;

            for (n = 0; n < userItems.length; ++n)
            {
                cCur = G(userItems.get()[n]);

                if (cCur.data('userType') === data['user_type'] && cCur.data('id') == data['user_id']) {
                    if (data['status'] === 'on') {
                        cCur.get().innerHTML = '<span class="green">在线</span>&nbsp;<span class="weight">' + data['nickname'] + '</span>';
                    } else {
                        cCur.get().innerHTML = '<span class="red">离线</span>&nbsp;<span class="weight">' + data['nickname'] + '</span>';
                    }
                }
            }
        }
    }
})();