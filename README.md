# 转盘抽奖系统 - FastAdmin 后端 + 微信小程序

## 系统架构

```
┌─────────────────────────────────────────────────────────┐
│                    微信小程序前端                        │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐     │
│  │  抽奖页面   │  │  结果弹窗   │  │  记录页面   │     │
│  └─────────────┘  └─────────────┘  └─────────────┘     │
└────────────────────────┬────────────────────────────────┘
                         │ HTTP API
┌────────────────────────▼────────────────────────────────┐
│               FastAdmin 后端 (ThinkPHP)                  │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐     │
│  │  Lottery    │  │  Activity   │  │  Record     │     │
│  │  Controller │  │  Model      │  │  Model      │     │
│  └─────────────┘  └─────────────┘  └─────────────┘     │
└────────────────────────┬────────────────────────────────┘
                         │ MySQL
┌────────────────────────▼────────────────────────────────┐
│                      MySQL 数据库                        │
│  ┌───────────┐ ┌───────────┐ ┌───────────┐ ┌────────┐ │
│  │ activity  │ │  prize    │ │  record   │ │ chance │ │
│  └───────────┘ └───────────┘ └───────────┘ └────────┘ │
└─────────────────────────────────────────────────────────┘
```

## 文件结构

```
/opt/data/lottery/
├── lottery.sql                 # 数据库表结构
├── admin/                       # 后台管理
│   └── view/
│       └── lottery/
│           ├── index.html       # 管理页面
│           ├── editActivity.html
│           └── editPrize.html
├── app/
│   ├── api/
│   │   └── controller/
│   │       └── Lottery.php      # 小程序API接口
│   └── common/
│       └── model/
│           ├── LotteryActivity.php
│           ├── LotteryPrize.php
│           ├── LotteryRecord.php
│           └── LotteryChance.php
└── weapp/                       # 微信小程序
    └── pages/
        └── lottery/
            ├── lottery.js
            ├── lottery.wxml
            ├── lottery.wxss
            └── records/
                ├── records.js
                ├── records.wxml
                └── records.wxss
```

## 安装步骤

### 1. 数据库导入

```sql
-- 登录MySQL
mysql -u root -p your_password

-- 创建数据库
CREATE DATABASE your_database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 导入表结构
USE your_database;
SOURCE /path/to/lottery.sql;
```

### 2. 后端部署 (FastAdmin)

1. **复制模型文件**:
   ```
   app/common/model/LotteryActivity.php
   app/common/model/LotteryPrize.php
   app/common/model/LotteryRecord.php
   app/common/model/LotteryChance.php
   ```

2. **复制控制器**:
   ```
   app/admin/controller/lottery/Index.php
   app/api/controller/Lottery.php
   ```

3. **复制后台视图**:
   ```
   admin/view/lottery/index.html
   admin/view/lottery/editActivity.html
   admin/view/lottery/editPrize.html
   ```

4. **配置路由** (可选，如果需要独立API路由):
   ```php
   // application/config.php 或 route.php
   Route::post('api/lottery/draw', 'api/lottery/draw');
   Route::get('api/lottery/index', 'api/lottery/index');
   ```

5. **添加后台菜单**:
   - 登录后台 -> 系统管理 -> 菜单管理
   - 添加抽奖管理菜单，链接: `lottery/index/index`

### 3. 小程序配置

1. **复制小程序代码**:
   - 将 `weapp/pages/lottery/` 复制到您的小程序 `pages/` 目录

2. **配置入口** (app.json):
   ```json
   {
     "pages": [
       "pages/lottery/lottery",
       "pages/lottery/records/records"
     ]
   }
   ```

3. **配置抽奖入口** (在您现有的首页添加按钮):
   ```html
   <button bindtap="goToLottery">抽奖活动</button>
   ```

   ```js
   goToLottery: function() {
     wx.navigateTo({
       url: '/pages/lottery/lottery'
     })
   }
   ```

4. **配置API请求域名**:
   - 登录微信公众平台
   - 开发管理 -> 开发设置 -> 服务器域名
   - 添加request合法域名

## 功能说明

### 管理后台功能

1. **抽奖活动管理**
   - 创建/编辑/删除抽奖活动
   - 设置活动时间、总抽奖次数、每日抽奖次数
   - 启用/禁用活动

2. **奖品管理**
   - 添加/编辑/删除奖品
   - 设置奖品类型(实物/积分/优惠券/谢谢参与)
   - 设置中奖权重(千分比)和库存

3. **抽奖记录**
   - 查看所有抽奖记录
   - 按用户ID/状态筛选
   - 发放实物奖品

### 小程序功能

1. **抽奖页面**
   - 大转盘动画
   - 实时显示剩余次数
   - 抽奖结果弹窗

2. **我的奖品**
   - 查看中奖记录
   - 按状态筛选(待领取/已领取)

## API 接口说明

| 接口 | 方法 | 说明 |
|------|------|------|
| `/api/lottery/index` | GET | 获取活动信息和奖品列表 |
| `/api/lottery/getChance` | GET | 获取用户剩余抽奖次数 |
| `/api/lottery/draw` | POST | 执行抽奖 |
| `/api/lottery/records` | GET | 获取用户抽奖记录 |

## 注意事项

1. **概率设置**: 所有奖品权重之和应为1000(千分比)，权重越高概率越大
2. **积分发放**: 积分自动发放，实物需后台手动发放
3. **库存管理**: 支持总库存和每日库存限制
4. **防刷**: 建议在生产环境添加IP限制和设备验证

## 二次开发

1. **接入现有用户系统**: 修改 `Lottery.php` 中的用户认证逻辑
2. **积分对接**: 修改 `addPoints()` 方法对接您的积分系统
3. **消息通知**: 在中奖后添加微信模板消息推送
4. **实物发放**: 在 `receivePrize()` 中对接物流系统