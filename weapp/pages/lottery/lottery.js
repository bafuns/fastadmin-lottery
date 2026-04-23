// pages/lottery/lottery.js
const app = getApp()
const api = require('../../utils/api.js')

Page({
  data: {
    activity: null,
    prizes: [],
    userChance: {
      total_remain: 0,
      today_remain: 0
    },
    isRolling: false,
    result: null,
    // 转盘配置
    prizesList: [],
    canvasWidth: 300,
    rotateAngle: 0,
    isDraw: false //是否在抽奖中
  },

  onLoad: function(options) {
    // 从首页进入，如果有activity_id则记录
    if (options.activity_id) {
      this.setData({ activity_id: options.activity_id })
    }
    this.loadActivity()
  },

  onShow: function() {
    if (this.data.activity) {
      this.getUserChance()
    }
  },

  // 加载活动信息
  loadActivity: function() {
    api.request({
      url: '/api/lottery/index',
      method: 'GET'
    }).then(res => {
      if (res.code === 1) {
        const activity = res.data.activity
        const prizes = res.data.prizes || []
        
        // 转换奖品数据用于转盘显示
        const prizesList = prizes.map((item, index) => ({
          id: item.id,
          name: item.name,
          image: item.image,
          color: this.getPrizeColor(index)
        }))

        this.setData({
          activity: activity,
          prizes: prizes,
          prizesList: prizesList,
          userChance: res.data.user_chance
        })

        // 绘制转盘
        this.drawWheel()
      } else {
        wx.showToast({
          title: res.msg || '暂无活动',
          icon: 'none'
        })
      }
    })
  },

  // 获取用户剩余次数
  getUserChance: function() {
    if (!this.data.activity) return
    
    api.request({
      url: '/api/lottery/getChance',
      data: { activity_id: this.data.activity.id }
    }).then(res => {
      if (res.code === 1) {
        this.setData({
          userChance: res.data
        })
      }
    })
  },

  // 获取奖品颜色
  getPrizeColor: function(index) {
    const colors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7', '#DDA0DD', '#98D8C8', '#F7DC6F']
    return colors[index % colors.length]
  },

  // 绘制转盘
  drawWheel: function() {
    const ctx = wx.createCanvasContext('wheelCanvas', this)
    const width = this.data.canvasWidth
    const center = width / 2
    const radius = width / 2 - 10
    const prizes = this.data.prizesList
    const num = prizes.length
    const angle = 2 * Math.PI / num

    // 背景
    ctx.setFillStyle('#FFF8E7')
    ctx.beginPath()
    ctx.arc(center, center, radius, 0, 2 * Math.PI)
    ctx.fill()

    // 绘制扇形和文字
    prizes.forEach((prize, i) => {
      const startAngle = i * angle - Math.PI / 2
      const endAngle = (i + 1) * angle - Math.PI / 2

      // 扇形
      ctx.setFillStyle(prize.color)
      ctx.beginPath()
      ctx.moveTo(center, center)
      ctx.arc(center, center, radius, startAngle, endAngle)
      ctx.closePath()
      ctx.fill()

      // 边框
      ctx.setStrokeStyle('#FFFFFF')
      ctx.setLineWidth(2)
      ctx.stroke()

      // 文字
      ctx.save()
      ctx.translate(center, center)
      ctx.rotate(startAngle + angle / 2)
      ctx.setFillStyle('#333333')
      ctx.setFontSize(12)
      ctx.setTextAlign('right')
      ctx.fillText(prize.name, radius - 20, 5)
      ctx.restore()
    })

    // 中心圆
    ctx.setFillStyle('#FFFFFF')
    ctx.beginPath()
    ctx.arc(center, center, 30, 0, 2 * Math.PI)
    ctx.fill()
    ctx.setStrokeStyle('#FF6B6B')
    ctx.setLineWidth(3)
    ctx.stroke()

    // 抽奖文字
    ctx.setFillStyle('#FF6B6B')
    ctx.setFontSize(16)
    ctx.setTextAlign('center')
    ctx.fillText('抽奖', center, center + 5)

    ctx.draw()
  },

  // 开始抽奖
  startLottery: function() {
    if (this.data.isRolling) return
    
    const chance = this.data.userChance
    if (chance.total_remain <= 0 || chance.today_remain <= 0) {
      wx.showToast({
        title: '抽奖次数已用完',
        icon: 'none'
      })
      return
    }

    this.setData({ isRolling: true })

    api.request({
      url: '/api/lottery/draw',
      method: 'POST',
      data: { activity_id: this.data.activity.id }
    }).then(res => {
      if (res.code === 1) {
        const prize = res.data.prize
        const prizesList = this.data.prizesList
        const prizeIndex = prizesList.findIndex(p => p.id === prize.id)
        
        // 计算旋转角度
        // 每个奖品占的角度
        const anglePerPrize = 360 / prizesList.length
        // 目标角度：需要旋转几圈 + 目标奖品的角度
        // 目标是让指针指向奖品中心，需要调整角度
        const targetAngle = 360 * 5 + (360 - prizeIndex * anglePerPrize - anglePerPrize / 2)
        
        this.animateRotate(targetAngle, () => {
          this.setData({
            isRolling: false,
            result: prize,
            userChance: res.data.remain
          })
          
          // 显示结果
          this.showResult(prize)
        })
      } else {
        this.setData({ isRolling: false })
        wx.showToast({
          title: res.msg || '抽奖失败',
          icon: 'none'
        })
      }
    }).catch(err => {
      this.setData({ isRolling: false })
      wx.showToast({
        title: '网络错误',
        icon: 'none'
      })
    })
  },

  // 动画旋转
  animateRotate: function(targetAngle, callback) {
    const duration = 5000 // 5秒
    const startTime = Date.now()
    const startAngle = 0
    
    const animate = () => {
      const now = Date.now()
      const progress = Math.min((now - startTime) / duration, 1)
      
      // 缓动函数
      const easeOut = 1 - Math.pow(1 - progress, 3)
      const currentAngle = startAngle + (targetAngle - startAngle) * easeOut
      
      this.setData({ rotateAngle: currentAngle })
      
      if (progress < 1) {
        requestAnimationFrame(animate)
      } else {
        callback && callback()
      }
    }
    
    animate()
  },

  // 显示结果弹窗
  showResult: function(prize) {
    wx.showModal({
      title: prize.type === 4 ? '谢谢参与' : '恭喜中奖',
      content: prize.name + (prize.value ? '：' + prize.value : ''),
      showCancel: false,
      confirmText: '知道了',
      success: () => {
        // 可以跳转到奖品列表页面
      }
    })
  },

  // 关闭结果弹窗
  closeResult: function() {
    this.setData({ result: null })
  },

  // 查看我的奖品
  goToRecords: function() {
    wx.navigateTo({
      url: '/pages/lottery/records/records'
    })
  },

  // 分享
  onShareAppMessage: function() {
    return {
      title: this.data.activity ? this.data.activity.title : '抽奖活动',
      path: '/pages/lottery/lottery?activity_id=' + (this.data.activity ? this.data.activity.id : '')
    }
  }
})