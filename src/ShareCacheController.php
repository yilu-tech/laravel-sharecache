<?php


namespace YiluTech\ShareCache;

use Illuminate\Http\Request;

class ShareCacheController
{
    public function put(ShareCacheManager $shareCacheManager, Request $request)
    {
        return $shareCacheManager->getServer()->put(
            $request->input('name'),
            $request->input('key')
        );
    }
}
