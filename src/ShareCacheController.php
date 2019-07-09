<?php


namespace YiluTech\ShareCache;

use Illuminate\Http\Request;

class ShareCacheController
{
    public function put(ShareCacheServiceManager $shareCacheManager, Request $request)
    {
        return $shareCacheManager->service()->put($request->input('name'), $request->input('key'));
    }
}
