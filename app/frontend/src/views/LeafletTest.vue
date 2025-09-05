<script setup lang="ts">
import 'leaflet/dist/leaflet.css'
import * as L from 'leaflet'

// アイコン画像のパスを設定（Vite で ?url を付ける）
import marker2x from 'leaflet/dist/images/marker-icon-2x.png?url'
import marker1x from 'leaflet/dist/images/marker-icon.png?url'
import shadow   from 'leaflet/dist/images/marker-shadow.png?url'
L.Icon.Default.mergeOptions({
  iconRetinaUrl: marker2x,
  iconUrl: marker1x,
  shadowUrl: shadow,
})

import { ref, onMounted } from 'vue'
import { LMap, LTileLayer, LMarker, LPopup } from '@vue-leaflet/vue-leaflet'

// 初期値は東京駅（位置情報が取れるまで仮置き）
const zoom = ref(13)
const center = ref<[number, number]>([35.681236, 139.767125])

onMounted(() => {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        center.value = [pos.coords.latitude, pos.coords.longitude]
      },
      (err) => {
        console.error("位置情報が取得できませんでした", err)
      }
    )
  } else {
    console.warn("このブラウザは Geolocation API に対応していません")
  }
})
</script>

<template>
  <l-map style="height: 400px" v-model:zoom="zoom" :center="center">
    <l-tile-layer
      url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
      attribution="&copy; OpenStreetMap contributors"
    />
    <l-marker :lat-lng="center">
      <l-popup>現在位置</l-popup>
    </l-marker>
  </l-map>
</template>
