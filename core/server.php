#!/usr/bin/env php
<?php

/**
 * Author: jyl
 * Date: 2020-03-09
 * Time: 17:11
 * Email: avril.leo@yahoo.com
 */

class WebsocketTest
{
    public $server;

    public function __construct()
    {
        $table = new Swoole\Table(1024);
        $table->column('fd', Swoole\Table::TYPE_INT);
        $table->column('name', Swoole\Table::TYPE_STRING, 64);
        $table->create();

        //创建websocket服务器对象，监听0.0.0.0:9502端口
        $this->server = new Swoole\WebSocket\Server("0.0.0.0", 9502);

        $this->server->table = $table;

        //监听WebSocket连接打开事件
        $this->server->on('open', function ($ws, $request) {

            $msg = [
                'num' => $ws->stats()['connection_num'],
                'message' => '有新的小伙伴进来了'
            ];

            // $server->connections 遍历所有websocket连接用户的fd，给所有用户推送
            foreach ($ws->connections as $fd) {
                // 需要先判断是否是正确的websocket连接，否则有可能会push失败
                if ($ws->isEstablished($fd)) {
                    $ws->push($fd, json_encode($msg, JSON_UNESCAPED_UNICODE));
                }
            }
        });

        //监听WebSocket消息事件
        $this->server->on('message', function ($ws, $frame) {

            $data = json_decode($frame->data, 1);

            if ($data && isset($data['name']) && empty($ws->table->get($frame->fd, 'name'))) {
                $ws->table->set($frame->fd, [
                    'fd' => $frame->fd,
                    'name' =>$data['name']
                ]);
            }

            if ($data && isset($data['message'])) {

                $msg = [
                    'num' => $ws->stats()['connection_num'],
                    'message' => '',
                    'me' => false,
                    'name' => ''
                ];

                foreach ($ws->connections as $fd) {
                    if ($ws->isEstablished($fd)) {
                        $msg['message'] = $data['message'];
                        $msg['name'] = $ws->table->get($frame->fd, 'name');
                        $msg['me'] = $fd == $frame->fd ? true : false;
                        $ws->push($fd, json_encode($msg, JSON_UNESCAPED_UNICODE));
                    }
                }
            }
        });

        //监听WebSocket连接关闭事件
        $this->server->on('close', function ($ws, $fd) {
            $msg = [
                'num' => $ws->stats()['connection_num'] - 1,
                'message' => ''
            ];

            foreach ($ws->connections as $fds) {
                if ($ws->isEstablished($fds)) {
                    $name = $ws->table->get($fd, 'name');
                    $msg['message'] = "{$name}退出群聊";
                    $ws->push($fds, json_encode($msg, JSON_UNESCAPED_UNICODE));
                }
            }
            $ws->table->del($fd);
        });

        $this->server->start();
    }
}

new WebsocketTest();