# MaSoThue.com Lookup

API tra cứu thông tin doanh nghiệp Việt Nam theo mã số thuế (MST).

## Features

- 🔍 Tra cứu thông tin công ty theo MST
- 📄 API trả về JSON
- 🌐 Giao diện web tra cứu
- 🔄 Hỗ trợ nhiều proxy (rotation)
- 📚 Tài liệu API đầy đủ

## Installation

```bash
# Clone repo
git clone https://github.com/user/masothue-lookup.git
cd masothue-lookup

# Install dependencies
composer install

# Configure proxy
cp proxies.txt.example proxies.txt
# Edit proxies.txt with your proxy credentials
```

## Usage

### Web Interface

Truy cập: `https://your-domain.com/`

### API

```bash
curl "https://your-domain.com/?mst=0315065353"
```

### Response

```json
{
    "success": true,
    "data": {
        "taxCode": "0315065353",
        "name": "CÔNG TY TNHH PHẦN MỀM HÀO QUANG VIỆT",
        "nameInternational": "HAO QUANG VIET SOFTWARE COMPANY LIMITED",
        "address": "Tầng 19, Indochina Park Tower...",
        "representative": "TRẦN VĂN QUYẾT",
        "phone": "02877796009",
        "status": "Đang hoạt động",
        ...
    }
}
```

## API Documentation

Xem chi tiết tại: `https://your-domain.com/docs.php`

## Response Fields

| Field | Description |
|-------|-------------|
| taxCode | Mã số thuế |
| name | Tên công ty |
| nameInternational | Tên quốc tế |
| nameShort | Tên viết tắt |
| address | Địa chỉ đầy đủ |
| addressLine1 | Số nhà, tên đường |
| city | Phường/Xã/Quận |
| stateProvince | Tỉnh/Thành phố |
| country | Quốc gia |
| representative | Người đại diện |
| phone | Số điện thoại |
| establishedDate | Ngày hoạt động |
| status | Tình trạng |
| businessType | Loại hình DN |
| businessSector | Ngành nghề chính |
| managedBy | Cơ quan thuế quản lý |

## HTTP Status Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 400 | Bad Request - Invalid MST format |
| 404 | Not Found - Company not found |
| 500 | Server Error |

## Requirements

- PHP >= 7.4
- Composer
- GuzzleHttp

## License

MIT License
